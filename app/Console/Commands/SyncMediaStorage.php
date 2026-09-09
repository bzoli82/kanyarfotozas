<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaStorage;
use App\Services\NasConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Egyszeri átállás segéd: a meglévő média-fájlokat átmásolja a mostani (dev)
 * diskekről az `config/media.php`-ban BEÁLLÍTOTT célokra (élesben Cloudflare R2):
 *
 *   - kis publikus fájlok (thumbnail / vízjeles előnézet / sprite / HLS) -> `MediaStorage::public()`
 *   - nagy fájlok (eredeti + letölthető JPEG/WebP/MP4)                     -> `MediaStorage::archive()`
 *
 * Használat (miután az .env-ben beállítottad az R2 diskeket):
 *   php artisan roadsidephoto:sync-media-storage --from-public=public --from-archive=local --dry-run
 *   php artisan roadsidephoto:sync-media-storage --from-public=public --from-archive=local
 *
 * Idempotens: a célon már meglévő (azonos méretű) fájlt kihagyja, kivéve --force.
 */
class SyncMediaStorage extends Command
{
    protected $signature = 'roadsidephoto:sync-media-storage
        {--from-public=public : Forrás disk a kis publikus fájloknak}
        {--from-archive= : Forrás disk a nagy fájloknak (üres = a media.original_storage szerinti)}
        {--force : A célon már meglévő fájlok felülírása is}
        {--dry-run : Csak kiírja, mit másolna}
        {--chunk=200 : Média rekordok kötegmérete}';

    protected $description = 'Meglévő média-fájlok átmásolása a beállított (R2) diskekre';

    private int $copied = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(NasConnection $nas): int
    {
        $publicTarget = MediaStorage::public();
        $archiveTarget = MediaStorage::archive();
        $fromPublic = (string) $this->option('from-public');
        $fromArchiveOpt = $this->option('from-archive');

        if (in_array('nas', [$archiveTarget, $fromArchiveOpt], true)) {
            $nas->applyRuntimeConfig();
        }

        $this->info("Publikus fájlok:  {$fromPublic}  ->  {$publicTarget}");
        $this->info('Nagy fájlok:      '.($fromArchiveOpt ?: '(original_storage szerint)')."  ->  {$archiveTarget}");
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — semmi nem másolódik.');
        }
        $this->newLine();

        Media::query()->chunkById((int) $this->option('chunk'), function ($chunk) use ($publicTarget, $archiveTarget, $fromPublic, $fromArchiveOpt): void {
            foreach ($chunk as $media) {
                $this->syncPublicFiles($media, $fromPublic, $publicTarget);
                $this->syncLargeFiles($media, $fromArchiveOpt, $archiveTarget);
            }
        });

        $this->newLine();
        $this->info("Kész — másolva: {$this->copied}, kihagyva: {$this->skipped}, hiba: {$this->failed}");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function syncPublicFiles(Media $media, string $from, string $to): void
    {
        $keys = array_filter([
            $media->thumbnail_s3_key,
            $media->watermarked_s3_key,
            $media->preview_sprite_s3_key,
        ]);

        foreach ($keys as $key) {
            $this->copy($from, $to, $key);
        }

        // HLS: a master.m3u8 mellett a teljes hls/{id}/ könyvtár.
        if ($media->hls_playlist_s3_key) {
            $dir = dirname($media->hls_playlist_s3_key);
            foreach (Storage::disk($from)->allFiles($dir) as $file) {
                $this->copy($from, $to, $file);
            }
        }
    }

    private function syncLargeFiles(Media $media, ?string $fromOption, string $to): void
    {
        $from = $fromOption ?: MediaStorage::diskForOriginal($media);

        $keys = array_filter(array_map(fn (string $field) => $media->{$field}, array_keys(Media::ARCHIVABLE_FIELDS)));

        $movedAny = false;
        foreach ($keys as $key) {
            if ($this->copy($from, $to, $key)) {
                $movedAny = true;
            }
        }

        if ($movedAny && $from !== $to && $media->original_storage !== Media::STORAGE_NAS && MediaStorage::hasArchiveTier()) {
            if (! $this->option('dry-run')) {
                $media->update(['original_storage' => Media::STORAGE_NAS, 'archived_at' => now()]);
            }
        }
    }

    private function copy(string $from, string $to, string $key): bool
    {
        if ($from === $to) {
            return false;
        }

        try {
            $source = Storage::disk($from);
            $target = Storage::disk($to);

            if (! $source->exists($key)) {
                return false;
            }

            if (! $this->option('force') && $target->exists($key) && $target->size($key) === $source->size($key)) {
                $this->skipped++;

                return false;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry] {$key}  ({$from} -> {$to})");
                $this->copied++;

                return true;
            }

            $stream = $source->readStream($key);
            $target->writeStream($key, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->copied++;

            return true;
        } catch (Throwable $e) {
            $this->failed++;
            $this->error("  HIBA {$key}: {$e->getMessage()}");

            return false;
        }
    }
}
