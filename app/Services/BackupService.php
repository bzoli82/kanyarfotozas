<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Napi adatbázis-mentés: `pg_dump` → gzip → a beállított diskre (`backups/`).
 * A régi mentéseket a `monitoring.backup_keep` szerint tartja meg.
 * Az eredeti média-fájlok nincsenek a mentésben — azok a NAS/R2 archív rétegen
 * élnek (saját redundanciával); a mentés a pótolhatatlan tranzakciós adatokra
 * fókuszál (rendelések, tokenek, beállítások, elszámolás).
 */
class BackupService
{
    public function __construct(private MonitoringSettings $settings) {}

    public function disk(): string
    {
        return (string) config('monitoring.backup_disk', 'local');
    }

    private function path(): string
    {
        return trim((string) config('monitoring.backup_path', 'backups'), '/');
    }

    /**
     * @return array{name: string, size: int, path: string}
     */
    public function run(): array
    {
        $connection = (string) config('database.default');
        $db = config("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException("A mentés csak PostgreSQL-t támogat (jelenlegi: {$connection}).");
        }

        $binary = (string) config('monitoring.pg_dump_binary', 'pg_dump');

        $result = Process::forever()
            ->timeout((int) config('monitoring.backup_timeout', 600))
            ->env(['PGPASSWORD' => (string) ($db['password'] ?? '')])
            ->run([
                $binary,
                '--host='.($db['host'] ?? '127.0.0.1'),
                '--port='.($db['port'] ?? '5432'),
                '--username='.($db['username'] ?? 'postgres'),
                '--no-owner',
                '--no-privileges',
                '--format=plain',
                '--dbname='.($db['database'] ?? ''),
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('pg_dump hiba: '.trim($result->errorOutput() ?: $result->output()) ?: 'ismeretlen');
        }

        $gz = gzencode($result->output(), 6);
        if ($gz === false) {
            throw new RuntimeException('A dump tömörítése nem sikerült.');
        }

        $name = 'db-'.now()->format('Y-m-d_His').'.sql.gz';
        $tmp = tempnam(sys_get_temp_dir(), 'kfbak');
        file_put_contents($tmp, $gz);

        try {
            Storage::disk($this->disk())->putFileAs($this->path(), new File($tmp), $name);
        } finally {
            @unlink($tmp);
        }

        $this->prune();
        $fullPath = $this->path().'/'.$name;
        $this->settings->recordBackup(true, null, $fullPath);

        return ['name' => $name, 'size' => strlen($gz), 'path' => $fullPath];
    }

    /**
     * A régi mentések törlése (a legutóbbi `backup_keep` marad).
     */
    public function prune(): void
    {
        $keep = max(1, (int) config('monitoring.backup_keep', 14));
        $files = collect(Storage::disk($this->disk())->files($this->path()))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sortDesc()
            ->values();

        $files->slice($keep)->each(fn ($f) => Storage::disk($this->disk())->delete($f));
    }

    /**
     * @return list<array{name: string, size: int, modified_at: string}>
     */
    public function list(int $limit = 20): array
    {
        $disk = Storage::disk($this->disk());

        return collect($disk->files($this->path()))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sortDesc()
            ->take($limit)
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => $disk->size($f),
                'modified_at' => Carbon::createFromTimestamp($disk->lastModified($f))->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function download(string $name): ?string
    {
        // Csak sima fájlnév — nincs útvonal-átjárás.
        if ($name !== basename($name) || ! str_ends_with($name, '.sql.gz')) {
            return null;
        }

        $key = $this->path().'/'.$name;

        return Storage::disk($this->disk())->exists($key) ? $key : null;
    }
}
