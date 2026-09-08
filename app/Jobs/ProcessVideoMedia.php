<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\PlateRecognition\PlateAnalysis;
use App\Services\PlateRecognition\PlateRecognitionManager;
use App\Services\VideoProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * EPIC-05 videofeldolgozo pipeline: a lokalisan tarolt eredeti videobol
 * legyartja a thumbnailt, a vizjelezett 720p elonezetet es a scrub sprite-ot,
 * szukseg eseten MP4-re konvertalja az eredetit, majd inditja a NAS-archivalast.
 *
 * "Prioritasos queue" (spec): a job a 'videos' queue-ra kerul — a worker-t
 * `php artisan queue:work --queue=videos,default` paranccsal erdemes inditani,
 * hogy a videok a kepek elott fussanak.
 *
 * AC: sikertelen feldolgozas haromszor probalkozik, utana status: failed.
 */
class ProcessVideoMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $mediaId)
    {
        $this->onQueue('videos');
    }

    public function handle(VideoProcessingService $video, ImageProcessingService $image, PlateRecognitionManager $plates): void
    {
        $media = Media::find($this->mediaId);

        if (! $media || ! $media->isVideo() || $media->status !== Media::STATUS_PROCESSING) {
            return;
        }

        $tmpDir = storage_path('app/tmp/video-'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);

        try {
            $publicDisk = Storage::disk(MediaStorage::public());

            $originalPath = Storage::disk(MediaStorage::STAGING)->path($media->original_s3_key);
            $probe = $video->probe($originalPath);

            $thumbnailKey = "thumbnails/{$media->id}.webp";
            $framePath = "{$tmpDir}/thumb-frame.png";
            $video->extractFrame($originalPath, min(1.0, $probe['duration']), $framePath);
            $publicDisk->put($thumbnailKey, $image->makeThumbnail($framePath));

            // EPIC-13: rendszámfelismerés a videó néhány kockáján (blur bake NINCS
            // videónál — a rendszám mozog; a médiaegészség panelen kap jelzést).
            $plate = $this->analyzePlates($plates, $video, $originalPath, $probe['duration'], $tmpDir);

            $watermarkedKey = "watermarked/{$media->id}.mp4";
            $watermarkedPath = "{$tmpDir}/watermarked.mp4";
            $video->makeWatermarkedPreview($originalPath, $probe['width'], $probe['height'], $watermarkedPath);
            $this->putStream($publicDisk, $watermarkedKey, $watermarkedPath);

            // HLS adaptiv stream (EPIC-16) — a vizjeles elonezetbol, 720p + 480p.
            $hlsKey = null;
            $hlsDir = "{$tmpDir}/hls";
            try {
                $hls = $video->makeHlsStream($originalPath, $probe['width'], $probe['height'], $hlsDir);
                foreach ($hls['files'] as $relativePath) {
                    $this->putStream($publicDisk, "hls/{$media->id}/{$relativePath}", "{$hlsDir}/{$relativePath}");
                }
                $hlsKey = "hls/{$media->id}/{$hls['master']}";
            } catch (Throwable $e) {
                report($e); // A HLS opcionalis — hiba eseten a lejátszó az MP4-re esik vissza.
            }

            $spriteKey = "sprites/{$media->id}.png";
            $spritePath = "{$tmpDir}/sprite.png";
            $sprite = $video->makeScrubSprite($originalPath, $probe['duration'], $spritePath);
            if ($sprite) {
                $this->putStream($publicDisk, $spriteKey, $spritePath);
            }

            $originalKey = $this->ensureMp4Original($media, $video, $originalPath, $tmpDir);

            $media->update([
                'thumbnail_s3_key' => $thumbnailKey,
                'watermarked_s3_key' => $watermarkedKey,
                'preview_sprite_s3_key' => $sprite ? $spriteKey : null,
                'preview_sprite_interval' => $sprite['interval'] ?? null,
                'hls_playlist_s3_key' => $hlsKey,
                'original_s3_key' => $originalKey,
                'width' => $probe['width'],
                'height' => $probe['height'],
                'duration_seconds' => (int) round($probe['duration']),
                'status' => Media::STATUS_READY,
                ...$plate->toMediaAttributes(),
                'license_plate_blurred' => false, // videónál nincs bake-elt homályosítás
            ]);

            ArchiveMediaOriginalToNas::dispatch($media->id);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    /**
     * Ha az eredeti nem mar MP4, ujrakonverzio egy MP4-re, es a regi fajl torlese —
     * a spec szerint a letoltheto video mindig MP4 (a Media modellben nincs kulon
     * "download video" mezo, az original_s3_key szolgal letoltheto forraskent is).
     */
    private function ensureMp4Original(Media $media, VideoProcessingService $video, string $originalPath, string $tmpDir): string
    {
        if (strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) === 'mp4') {
            return $media->original_s3_key;
        }

        $remuxedPath = "{$tmpDir}/remuxed.mp4";
        $video->remuxToMp4($originalPath, $remuxedPath);

        $newKey = preg_replace('/\.[^.]+$/', '.mp4', $media->original_s3_key);
        $this->putStream(Storage::disk(MediaStorage::STAGING), $newKey, $remuxedPath);
        Storage::disk(MediaStorage::STAGING)->delete($media->original_s3_key);

        return $newKey;
    }

    private function analyzePlates(
        PlateRecognitionManager $plates,
        VideoProcessingService $video,
        string $originalPath,
        float $duration,
        string $tmpDir,
    ): PlateAnalysis {
        if (! $plates->isEnabled()) {
            return PlateAnalysis::skipped();
        }

        $frames = [];
        foreach ((array) config('media.plate_video_sample_seconds', [1, 5, 10]) as $i => $second) {
            if ($second > $duration) {
                continue;
            }
            $path = "{$tmpDir}/plate-frame-{$i}.png";
            $video->extractFrame($originalPath, (float) $second, $path);
            $frames[] = $path;
        }

        return $plates->analyzeFrames($frames);
    }

    private function putStream(Filesystem $disk, string $key, string $absolutePath): void
    {
        $stream = fopen($absolutePath, 'r');
        $disk->put($key, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function failed(Throwable $exception): void
    {
        Media::whereKey($this->mediaId)->update(['status' => Media::STATUS_FAILED]);
    }
}
