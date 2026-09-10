<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\ArchiveStorage;
use Illuminate\Console\Command;

/**
 * Az archivált média fájljainak másolása a saját SFTP/NAS és a Cloudflare R2
 * privát bucket között — a /admin/settings/storage „Szinkron" gombjának CLI
 * megfelelője (nagy adatmennyiséghez, ha nincs queue worker).
 *
 *   php artisan roadsidephoto:sync-archive-storage --from=nas --to=r2_private
 *   php artisan roadsidephoto:sync-archive-storage --from=r2_private --to=nas --force
 *
 * Idempotens: a célon már meglévő, azonos méretű fájlt kihagyja (kivéve --force).
 */
class SyncArchiveStorage extends Command
{
    protected $signature = 'roadsidephoto:sync-archive-storage
        {--from= : Forrás disk (nas vagy r2_private)}
        {--to= : Cél disk (nas vagy r2_private)}
        {--force : A célon már meglévő fájlok felülírása is}
        {--chunk=250 : Média rekordok kötegmérete}';

    protected $description = 'Archív média fájlok szinkronizálása NAS <-> Cloudflare R2 között';

    public function handle(ArchiveStorage $archive): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');

        if (! in_array($from, ArchiveStorage::CHOICES, true) || ! in_array($to, ArchiveStorage::CHOICES, true) || $from === $to) {
            $this->error('A --from és --to értéke különböző kell legyen, és mindkettő: '.implode(' vagy ', ArchiveStorage::CHOICES));

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $copied = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("Archív szinkron: {$from}  ->  {$to}".($force ? '  (force)' : ''));

        Media::query()
            ->where('original_storage', Media::STORAGE_NAS)
            ->pluck('id')
            ->chunk(max(10, (int) $this->option('chunk')))
            ->each(function ($ids) use ($archive, $from, $to, $force, &$copied, &$skipped, &$failed): void {
                $result = $archive->copyChunk($ids->all(), $from, $to, $force);
                $copied += $result['copied'];
                $skipped += $result['skipped'];
                $failed += $result['failed'];
                $this->line("  …másolva: {$copied}, kihagyva: {$skipped}, hiba: {$failed}");
            });

        $this->newLine();
        $this->info("Kész — másolva: {$copied}, kihagyva: {$skipped}, hiba: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
