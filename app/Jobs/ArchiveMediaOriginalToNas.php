<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\MediaStorage;
use App\Services\NasConnection;
use App\Services\NasPathGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Athelyezi egy media eredeti + letoltheto (JPEG/WebP) fajljait a lokalis
 * staging diskrol az ARCHIV diskre (SFTP NAS, vagy elesben Cloudflare R2 —
 * lasd `config/media.php` `disks.archive`), hogy a nagy fajlok ne a
 * webhosting tarhelyet fogyasszak. A kis fajlok (thumbnail, vizjelezett
 * elonezet, sprite) mindig a publikus diskan maradnak.
 *
 * Ha nincs kulon archiv reteg (`MEDIA_ARCHIVE_DISK=local`), a job azonnal
 * visszater — az eredeti a `local` diskon marad.
 *
 * Idempotens: ujrafuttataskor csak azokat a mezoket dolgozza fel, amik meg
 * a staging diskon vannak — a mar athelyezetteket kihagyja.
 */
class ArchiveMediaOriginalToNas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600, 14400];

    public function __construct(public int $mediaId) {}

    public function handle(NasPathGenerator $paths, NasConnection $nasConnection): void
    {
        $media = Media::find($this->mediaId);

        if (! $media || $media->original_storage === Media::STORAGE_NAS) {
            return;
        }

        // Nincs kulon archiv reteg (dev alapertelmezes lehet) — az eredeti marad a stagingen.
        if (! MediaStorage::hasArchiveTier()) {
            return;
        }

        $archiveDisk = MediaStorage::archive();

        // SFTP NAS eseten a kapcsolati adatok a site_settings-bol jonnek; R2/S3 eseten
        // a config/filesystems.php + env eleg, nincs runtime injektalas.
        if ($archiveDisk === 'nas') {
            $nasConnection->applyRuntimeConfig();
        }

        $stagingDisk = Storage::disk(MediaStorage::STAGING);
        $targetDisk = Storage::disk($archiveDisk);

        $anyMovedNow = false;
        $anyStillLocal = false;

        foreach (Media::ARCHIVABLE_FIELDS as $field => $config) {
            $currentKey = $media->{$field};

            if (! $currentKey) {
                continue;
            }

            if (! $stagingDisk->exists($currentKey)) {
                // Mar athelyezve egy korabbi (reszben sikeres) futaskor, vagy sosem volt lokalis.
                continue;
            }

            $extension = pathinfo($currentKey, PATHINFO_EXTENSION);
            $archiveKey = $paths->pathFor($media, $field, $extension);

            try {
                $stream = $stagingDisk->readStream($currentKey);
                $targetDisk->writeStream($archiveKey, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $stagingDisk->delete($currentKey);
                $media->{$field} = $archiveKey;
                $anyMovedNow = true;
            } catch (Throwable $e) {
                $anyStillLocal = true;
                Log::warning("NAS archivalas sikertelen media #{$media->id} ({$field}): {$e->getMessage()}");
            }
        }

        $media->archive_attempts++;

        if ($anyStillLocal) {
            $media->archive_error = 'Egy vagy tobb fajl athelyezese nem sikerult — a job ujraprobalkozik.';
            $media->save();

            throw new \RuntimeException("Media #{$media->id} NAS archivalasa reszlegesen sikertelen.");
        }

        if ($anyMovedNow || $media->original_storage !== Media::STORAGE_NAS) {
            $media->original_storage = Media::STORAGE_NAS;
            $media->archived_at = now();
            $media->archive_error = null;
        }

        $media->save();
    }

    public function failed(Throwable $exception): void
    {
        $media = Media::find($this->mediaId);

        if ($media) {
            $media->archive_error = 'Vegleges hiba a NAS archivalas soran: '.$exception->getMessage();
            $media->save();
        }
    }
}
