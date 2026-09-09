<?php

namespace App\Console\Commands;

use App\Services\FtpImport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A böngészőből közvetlenül R2-be feltöltött, de importra soha nem került
 * ideiglenes mappák (`_upload/{event}/{session}`) takarítása. A sikeres import
 * a batch végén magától törli a sajátját — ez a backstop az árva session-ökre.
 *
 * A session-mappa neve `Ymd-His-<random>`, így a kor a névből olvasható
 * (nincs S3 lastModified-hívás).
 */
class PurgeImportUploads extends Command
{
    protected $signature = 'roadsidephoto:purge-import-uploads {--hours=48 : Ennél régebbi feltöltő-mappák törlése}';

    protected $description = 'Árva közvetlen-feltöltés mappák törlése az import-tárolóból';

    public function handle(FtpImport $import): int
    {
        if (! $import->providesDirectUpload()) {
            $this->info('Az import-disk nem támogat közvetlen feltöltést — nincs mit takarítani.');

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now()->subHours((int) $this->option('hours'));
        $disk = Storage::disk(FtpImport::disk());
        $deleted = 0;

        $roots = ['_upload'];
        foreach (rescue(fn () => $disk->directories((string) config('media.import_photographer_folder', 'fotosok')), [], false) as $photographerDir) {
            $roots[] = "{$photographerDir}/_upload";
        }

        foreach ($roots as $root) {
            foreach (rescue(fn () => $disk->directories($root), [], false) as $eventDir) {
                foreach (rescue(fn () => $disk->directories($eventDir), [], false) as $sessionDir) {
                    if ($this->stale(basename($sessionDir), $cutoff)) {
                        rescue(fn () => $disk->deleteDirectory($sessionDir), null, false);
                        $deleted++;
                    }
                }
            }
        }

        $this->info("Törölt árva feltöltő-mappa: {$deleted}");

        return self::SUCCESS;
    }

    private function stale(string $name, CarbonImmutable $cutoff): bool
    {
        if (! preg_match('/^(\d{8})-(\d{6})/', $name, $m)) {
            return false; // ismeretlen formátum — nem nyúlunk hozzá
        }

        $created = rescue(fn () => CarbonImmutable::createFromFormat('Ymd His', "{$m[1]} {$m[2]}"), null, false);

        return $created !== null && $created->lessThan($cutoff);
    }
}
