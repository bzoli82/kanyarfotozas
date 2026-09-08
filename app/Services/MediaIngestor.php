<?php

namespace App\Services;

use App\Jobs\ProcessImageMedia;
use App\Jobs\ProcessVideoMedia;
use App\Models\Event;
use App\Models\Media;
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

    public function isDuplicate(Event $event, string $hash): bool
    {
        return Media::query()
            ->where('event_id', $event->id)
            ->where('content_hash', $hash)
            ->exists();
    }
}
