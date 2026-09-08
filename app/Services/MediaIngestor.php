<?php

namespace App\Services;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Jobs\ProcessImageMedia;
use App\Jobs\ProcessVideoMedia;
use App\Models\Event;
use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Egy stagingbe letöltött/feltöltött eredeti fájlból `Media` rekordot hoz létre
 * és elindítja a feldolgozó pipeline-t — közös út a webes feltöltés
 * (`Admin\MediaController`) és az FTP-import (`FtpImport`) mögött.
 *
 * Két közös szabály itt él egy helyen:
 *  - **Duplikátum-szűrés**: a fájl SHA-256 lenyomata eseményenként egyedi, így
 *    ugyanaz a fájl nem kerül be kétszer ugyanabba a galériába.
 *  - **Esemény-szintű árazás**: az új média ára az esemény foto/videó árából jön
 *    (nem fájlonként állítjuk).
 */
class MediaIngestor
{
    public function __construct(private ImageProcessingService $images) {}

    /**
     * @return Media|null a létrejött média, vagy null ha duplikátum volt (a staging fájlt ilyenkor törli)
     */
    public function ingestStaged(
        Event $event,
        string $photographerId,
        string $stagingKey,
        string $type,
        ?string $importSourcePath = null,
    ): ?Media {
        $staging = Storage::disk(MediaStorage::STAGING);
        $hash = hash_file('sha256', $staging->path($stagingKey)) ?: null;

        if ($hash !== null && $this->isDuplicate($event, $hash)) {
            $staging->delete($stagingKey);

            return null;
        }

        $media = Media::create([
            'event_id' => $event->id,
            'photographer_id' => $photographerId,
            'type' => $type,
            'original_s3_key' => $stagingKey,
            'content_hash' => $hash,
            'import_source_path' => $importSourcePath,
            'status' => Media::STATUS_PROCESSING,
            'price_cents' => $event->priceFor($type),
        ]);

        if ($type === Media::TYPE_VIDEO) {
            ProcessVideoMedia::dispatch($media->id);
        } else {
            ProcessImageMedia::dispatch($media->id);
        }

        return $media;
    }

    /**
     * Elő-feldolgozott videó (`media.video_mode` = `preprocessed`): a fotós a helyi
     * gépén elkészített fájl-hármast tölti fel. NINCS szerver-oldali FFmpeg —
     * a média azonnal `ready`.
     *
     *  - `$originalStagingKey` : a teljes felbontású eredeti (a termék + letölthető forrás)
     *  - `$loresStagingKey`    : a kis felbontású, vízjelezett előnézet → `watermarked_s3_key` (publikus disk)
     *  - `$posterStagingKey`   : opcionális állókép → thumbnail (GD, publikus disk)
     *
     * @return Media|null a létrejött média, vagy null ha duplikátum volt (a staging fájlokat ilyenkor törli)
     */
    public function ingestPreprocessedVideo(
        Event $event,
        string $photographerId,
        string $originalStagingKey,
        string $loresStagingKey,
        ?string $posterStagingKey = null,
        ?string $importSourcePath = null,
    ): ?Media {
        $staging = Storage::disk(MediaStorage::STAGING);
        $hash = hash_file('sha256', $staging->path($originalStagingKey)) ?: null;

        if ($hash !== null && $this->isDuplicate($event, $hash)) {
            $staging->delete($originalStagingKey);
            $staging->delete($loresStagingKey);
            if ($posterStagingKey !== null) {
                $staging->delete($posterStagingKey);
            }

            return null;
        }

        $media = Media::create([
            'event_id' => $event->id,
            'photographer_id' => $photographerId,
            'type' => Media::TYPE_VIDEO,
            'original_s3_key' => $originalStagingKey,
            'content_hash' => $hash,
            'import_source_path' => $importSourcePath,
            'status' => Media::STATUS_READY,
            'price_cents' => $event->priceFor(Media::TYPE_VIDEO),
        ]);

        $public = Storage::disk(MediaStorage::public());

        $watermarkedKey = "watermarked/{$media->id}.mp4";
        $this->moveToDisk($staging, $public, $loresStagingKey, $watermarkedKey);

        $thumbnailKey = null;
        if ($posterStagingKey !== null) {
            $thumbnailKey = "thumbnails/{$media->id}.webp";
            $public->put($thumbnailKey, $this->images->makeThumbnail($staging->path($posterStagingKey)));
            $staging->delete($posterStagingKey);
        }

        $media->update([
            'watermarked_s3_key' => $watermarkedKey,
            'thumbnail_s3_key' => $thumbnailKey,
        ]);

        ArchiveMediaOriginalToNas::dispatch($media->id);

        return $media;
    }

    private function moveToDisk(Filesystem $from, Filesystem $to, string $sourceKey, string $targetKey): void
    {
        $stream = $from->readStream($sourceKey);
        $to->writeStream($targetKey, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $from->delete($sourceKey);
    }

    public function isDuplicate(Event $event, string $hash): bool
    {
        return Media::query()
            ->where('event_id', $event->id)
            ->where('content_hash', $hash)
            ->exists();
    }
}
