<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\PlateRecognition\PlateRecognitionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * EPIC-04 kepfeldolgozo pipeline: a lokalisan tarolt eredeti kepbol legyartja
 * a 4 valtozatot (thumbnail, vizjelezett elonezet, letoltheto JPEG/WebP),
 * majd — siker eseten — a NAS-archivalo jobot inditja el az eredeti +
 * letoltheto fajlokra.
 *
 * AC: sikertelen feldolgozas haromszor probalkozik, utana status: failed.
 */
class ProcessImageMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $mediaId) {}

    public function handle(ImageProcessingService $processor, PlateRecognitionManager $plates): void
    {
        $media = Media::find($this->mediaId);

        if (! $media || ! $media->isPhoto() || $media->status !== Media::STATUS_PROCESSING) {
            return;
        }

        $originalPath = Storage::disk(MediaStorage::STAGING)->path($media->original_s3_key);

        $dimensions = $processor->dimensions($originalPath);
        $shotAt = $processor->readShotAt($originalPath);

        // EPIC-13: rendszámfelismerés (ki van kapcsolva / nincs kulcs => skipped, semmi nem történik).
        $plate = $plates->analyze($originalPath);
        $blurBox = $plate->shouldBlur ? $plate->detection->box : null;

        $publicDisk = Storage::disk(MediaStorage::public());
        $stagingDisk = Storage::disk(MediaStorage::STAGING);

        $thumbnailKey = "thumbnails/{$media->id}.webp";
        $publicDisk->put($thumbnailKey, $processor->makeThumbnail($originalPath));

        $watermarkedKey = "watermarked/{$media->id}.webp";
        $publicDisk->put($watermarkedKey, $processor->makeWatermarkedPreview($originalPath, $blurBox));

        $downloadJpegKey = "downloads/{$media->id}.jpg";
        $stagingDisk->put($downloadJpegKey, $processor->makeDownloadJpeg($originalPath, $blurBox));

        $downloadWebpKey = "downloads/{$media->id}.webp";
        $stagingDisk->put($downloadWebpKey, $processor->makeDownloadWebp($originalPath, $blurBox));

        $media->update([
            'thumbnail_s3_key' => $thumbnailKey,
            'watermarked_s3_key' => $watermarkedKey,
            'download_jpeg_s3_key' => $downloadJpegKey,
            'download_webp_s3_key' => $downloadWebpKey,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'shot_at' => $shotAt,
            'status' => Media::STATUS_READY,
            ...$plate->toMediaAttributes(),
        ]);

        ArchiveMediaOriginalToNas::dispatch($media->id);
    }

    public function failed(Throwable $exception): void
    {
        Media::whereKey($this->mediaId)->update(['status' => Media::STATUS_FAILED]);
    }
}
