<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\NasConnection;
use App\Services\PlateRecognition\PlateRecognitionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EPIC-13: azokra a KÉSZ fotókra futtatja a rendszámfelismerést, amelyek még
 * `pending` státuszúak — tipikusan azért, mert a funkció a feldolgozásuk után
 * lett bekapcsolva. Ha `auto_blur` mód aktív és a találat megbízható, a
 * vízjeles előnézetet + a letölthető változatokat is újragenerálja homályosítva.
 */
class AnalyzePendingPlates extends Command
{
    protected $signature = 'roadsidephoto:analyze-plates {--limit=200 : Legfeljebb ennyi médiafájl egy futásban}';

    protected $description = 'Rendszámfelismerés a még feldolgozatlan (pending) kész fotókra';

    public function handle(PlateRecognitionManager $plates, ImageProcessingService $processor): int
    {
        if (! $plates->isEnabled()) {
            $this->warn('A rendszámfelismerés ki van kapcsolva vagy nincs API kulcs — nincs teendő.');

            return self::SUCCESS;
        }

        $media = Media::query()
            ->where('type', Media::TYPE_PHOTO)
            ->where('status', Media::STATUS_READY)
            ->where('license_plate_status', Media::PLATE_PENDING)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($media->isEmpty()) {
            $this->info('Nincs feldolgozandó fotó.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($media->count());
        $tmpDir = storage_path('app/tmp/analyze-plates-'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);

        try {
            foreach ($media as $item) {
                $originalDisk = MediaStorage::diskForOriginal($item);

                if ($originalDisk === 'nas') {
                    app(NasConnection::class)->applyRuntimeConfig();
                }

                // Az Intervention valós fájl-útvonalat igényel — ha az eredeti nem a
                // lokális staging-en van (NAS / R2), ideiglenesen letöltjük.
                if ($originalDisk === MediaStorage::STAGING) {
                    $originalPath = Storage::disk(MediaStorage::STAGING)->path($item->original_s3_key);
                    $tempOriginal = null;
                } else {
                    $originalPath = $tempOriginal = $tmpDir.'/'.basename($item->original_s3_key);
                    File::put($originalPath, Storage::disk($originalDisk)->get($item->original_s3_key));
                }

                $analysis = $plates->analyze($originalPath);
                $blurBox = $analysis->shouldBlur ? $analysis->detection->box : null;

                if ($blurBox) {
                    Storage::disk(MediaStorage::public())->put($item->watermarked_s3_key, $processor->makeWatermarkedPreview($originalPath, $blurBox));
                    Storage::disk($originalDisk)->put($item->download_jpeg_s3_key, $processor->makeDownloadJpeg($originalPath, $blurBox));
                    Storage::disk($originalDisk)->put($item->download_webp_s3_key, $processor->makeDownloadWebp($originalPath, $blurBox));
                }

                if ($tempOriginal !== null) {
                    File::delete($tempOriginal);
                }

                $item->update($analysis->toMediaAttributes());
                $bar->advance();
            }
        } finally {
            File::deleteDirectory($tmpDir);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info($media->count().' fotó feldolgozva.');

        return self::SUCCESS;
    }
}
