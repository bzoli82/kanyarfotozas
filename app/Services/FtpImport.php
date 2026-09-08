<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Support\PreprocessedVideoGrouper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tömeges média-import egy külső tárolóból egy eseményhez. A forrás-disk
 * `config('media.import_disk')` szerint:
 *
 *   nas       – SFTP fájlszerver (a `NasConnection` kulcsaival)
 *   r2_import – dedikált Cloudflare R2 „drop zone" bucket (rclone / S3-kliens tölti fel)
 *   local     – a szerver egy helyi mappája (`storage/app/private` alatt)
 *
 * Az admin az esemény oldalán kiválaszt fájlokat VAGY egy egész mappát (rekurzívan
 * kibontjuk), a rendszer a lokális stagingbe tölti őket, majd a `MediaIngestor` +
 * a szokásos feldolgozó pipeline legyártja a thumbnailt, a vízjeles előnézetet és
 * a letölthető változatokat, végül R2-be archivál. 100+ fájlnál a művelet a
 * `imports` queue-n, batch job-ban fut (App\Jobs\ImportMediaChunk), folyamatjelzővel.
 *
 * @phpstan-type ImportUnit array{type: 'photo', path: string}|array{type: 'video', master: string, lores: string, poster: string|null}
 */
class FtpImport
{
    /** A `paths[]` nyers tömb hossz-korlátja (mappákkal együtt — nem a fájlszám). */
    public const MAX_PER_IMPORT = 500;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi'];

    public function __construct(private NasConnection $nas, private MediaIngestor $ingestor) {}

    /** A beállított import forrás-disk neve. */
    public static function disk(): string
    {
        return (string) config('media.import_disk', 'nas');
    }

    public function isAvailable(): bool
    {
        $disk = self::disk();

        if ($disk === 'nas') {
            return $this->nas->isConfigured();
        }

        if (! is_array(config("filesystems.disks.{$disk}"))) {
            return false;
        }

        // Tényleges elérhetőség (egy listázás), 5 percre gyorsítótárazva, hogy az
        // esemény-oldal betöltése ne kezdeményezzen minden alkalommal S3-hívást.
        return (bool) Cache::remember("media.import_disk_available.{$disk}", now()->addMinutes(5), fn (): bool => rescue(function () use ($disk): bool {
            Storage::disk($disk)->directories('');

            return true;
        }, false, false));
    }

    private function applyRuntimeConfigIfNeeded(): void
    {
        if (self::disk() === 'nas') {
            $this->nas->applyRuntimeConfig();
        }
    }

    /** Elő-feldolgozott videó-mód: a böngésző videókat is mutat, az import párosít. */
    private function isPreprocessed(): bool
    {
        return config('media.video_mode') === 'preprocessed';
    }

    /**
     * @return list<string> A böngészőben látható / importálható fájlkiterjesztések.
     */
    private function browsableExtensions(): array
    {
        return $this->isPreprocessed()
            ? [...self::IMAGE_EXTENSIONS, ...self::VIDEO_EXTENSIONS]
            : self::IMAGE_EXTENSIONS;
    }

    /**
     * Egy távoli könyvtár tartalma: almappák + fájlok.
     *
     * @return array{
     *     path: string,
     *     segments: list<array{name: string, path: string}>,
     *     directories: list<array{name: string, path: string, file_count: int|null}>,
     *     files: list<array{name: string, path: string, size: int|null, last_modified: string|null}>
     * }
     */
    public function browse(string $path = ''): array
    {
        $path = $this->normalize($path);
        $this->applyRuntimeConfigIfNeeded();
        $disk = Storage::disk(self::disk());

        $rawDirs = $disk->directories($path);
        // A fájlszám-előnézet (mappánként egy listázás) csak kevés almappánál éri meg.
        $countFiles = count($rawDirs) <= 12;

        $directories = collect($rawDirs)
            ->map(fn (string $dir): array => [
                'name' => basename($dir),
                'path' => $this->normalize($dir),
                'file_count' => $countFiles ? $this->safeFileCount($disk, $dir) : null,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $loresSuffix = (string) config('media.preprocessed_lores_suffix', '_lores');
        $browsable = $this->browsableExtensions();

        $files = collect($disk->files($path))
            ->filter(fn (string $file): bool => in_array(
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                $browsable,
                true,
            ))
            // A kis felbontású előnézetet nem lehet külön kiválasztani — az import
            // a mester videó mellé automatikusan behúzza.
            ->reject(fn (string $file): bool => $this->isPreprocessed()
                && $loresSuffix !== ''
                && str_ends_with(pathinfo($file, PATHINFO_FILENAME), $loresSuffix)
                && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true))
            ->map(fn (string $file): array => [
                'name' => basename($file),
                'path' => $this->normalize($file),
                'size' => $this->safeSize($disk, $file),
                'last_modified' => $this->safeLastModified($disk, $file),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'path' => $path,
            'segments' => $this->breadcrumb($path),
            'directories' => $directories,
            'files' => $files,
        ];
    }

    /**
     * A nyers kijelölést (fájlok ÉS/VAGY mappák) importálandó „egységekre" bontja:
     * a mappákat rekurzívan kibontja, szűri a kiterjesztést, preprocessed módban a
     * videó-hármasokat (`.mp4` + `_lores.mp4` + `.jpg`) alapnév szerint párosítja.
     *
     * @param  list<string>  $paths
     * @return array{units: list<ImportUnit>, total: int}
     */
    public function planImport(array $paths): array
    {
        $this->applyRuntimeConfigIfNeeded();
        $disk = Storage::disk(self::disk());

        $files = [];

        foreach ($paths as $raw) {
            try {
                $p = $this->normalize((string) $raw);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($p === '') {
                continue;
            }

            if ($this->isDirectory($disk, $p)) {
                foreach (rescue(fn () => $disk->allFiles($p), [], false) as $f) {
                    $files[] = $f;
                }
            } else {
                $files[] = $p;
            }
        }

        $browsable = $this->browsableExtensions();

        $files = collect($files)
            ->unique()
            ->filter(fn (string $f): bool => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $browsable, true))
            ->values()
            ->all();

        $units = $this->isPreprocessed()
            ? $this->groupPreprocessedUnits($files)
            : array_map(fn (string $f): array => ['type' => 'photo', 'path' => $f], $files);

        return ['units' => array_values($units), 'total' => count($units)];
    }

    /**
     * Egyetlen import-egység feldolgozása (a batch job chunkjaiból és a szinkron
     * `import()`-ból is ez hívódik).
     *
     * @param  ImportUnit  $unit
     * @return 'imported'|'skipped'|'failed'
     */
    public function importUnit(Event $event, array $unit, string $photographerId): string
    {
        $this->applyRuntimeConfigIfNeeded();
        $remote = Storage::disk(self::disk());
        $staging = Storage::disk(MediaStorage::STAGING);

        $sourcePath = $unit['type'] === 'video' ? $unit['master'] : $unit['path'];

        // Gyors elő-szűrés: ugyanezt a forrás-útvonalat már importáltuk ide.
        if (Media::query()->where('event_id', $event->id)->where('import_source_path', $sourcePath)->exists()) {
            return 'skipped';
        }

        if ($unit['type'] === 'video') {
            return $this->importPreprocessedUnit($event, $unit, $photographerId, $remote, $staging);
        }

        $key = $this->stageRemote($event, $unit['path'], strtolower(pathinfo($unit['path'], PATHINFO_EXTENSION)), $remote, $staging);

        if ($key === null) {
            return 'failed';
        }

        return $this->ingestor->ingestStaged($event, $photographerId, $key, Media::TYPE_PHOTO, $unit['path']) !== null
            ? 'imported'
            : 'skipped';
    }

    /**
     * Szinkron import (új-esemény űrlap + kis kötegek). A háttér-batch az
     * `App\Jobs\ImportMediaChunk`-ban ugyanezt az `importUnit()`-ot hívja.
     *
     * @param  list<string>  $paths
     * @return array{imported: int, skipped: int, failed: list<string>}
     */
    public function import(Event $event, array $paths, string $photographerId): array
    {
        $plan = $this->planImport($paths);

        $imported = 0;
        $skipped = 0;
        $failed = [];

        foreach (array_slice($plan['units'], 0, (int) config('media.import_hard_cap', 20000)) as $unit) {
            $outcome = $this->importUnit($event, $unit, $photographerId);

            match ($outcome) {
                'imported' => $imported++,
                'skipped' => $skipped++,
                default => $failed[] = $unit['path'] ?? $unit['master'],
            };
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @param  list<string>  $files
     * @return list<ImportUnit>
     */
    private function groupPreprocessedUnits(array $files): array
    {
        $disk = Storage::disk(self::disk());
        $suffix = (string) config('media.preprocessed_lores_suffix', '_lores');

        $byDir = [];

        foreach ($files as $f) {
            $byDir[$this->dirOf($f)][basename($f)] = $f;
        }

        $units = [];

        foreach ($byDir as $nameToPath) {
            $groups = PreprocessedVideoGrouper::group($nameToPath);

            foreach ($groups['videos'] as $video) {
                // A `_lores` / poszter testvér a kijelölésben lehet, VAGY a tárolón
                // (ha csak a mester `.mp4`-et jelölte ki az admin) — mindkettőt nézzük.
                $lores = $video['lores'] ?? $this->probeSibling($disk, $video['master'], $suffix, self::VIDEO_EXTENSIONS);

                if ($lores === null) {
                    continue; // _lores nélkül a videó kimarad
                }

                $units[] = [
                    'type' => 'video',
                    'master' => $video['master'],
                    'lores' => $lores,
                    'poster' => $video['poster'] ?? $this->probeSibling($disk, $video['master'], '', PreprocessedVideoGrouper::POSTER_EXTENSIONS),
                ];
            }

            foreach ($groups['photos'] as $photo) {
                $units[] = ['type' => 'photo', 'path' => $photo];
            }
        }

        return $units;
    }

    /**
     * A mester videó mellé keres egy testvér-fájlt a tárolón: {alapnév}{suffix}.{ext}.
     *
     * @param  list<string>  $extensions
     */
    private function probeSibling(Filesystem $disk, string $masterPath, string $suffix, array $extensions): ?string
    {
        $dir = $this->dirOf($masterPath);
        $prefix = $dir === '.' ? '' : $dir.'/';
        $stem = pathinfo($masterPath, PATHINFO_FILENAME);

        foreach ($extensions as $ext) {
            $candidate = "{$prefix}{$stem}{$suffix}.{$ext}";

            if (rescue(fn (): bool => $disk->fileExists($candidate), false, false)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array{type: 'video', master: string, lores: string, poster: string|null}  $unit
     * @return 'imported'|'skipped'|'failed'
     */
    private function importPreprocessedUnit(
        Event $event,
        array $unit,
        string $photographerId,
        Filesystem $remote,
        Filesystem $staging,
    ): string {
        $masterKey = $this->stageRemote($event, $unit['master'], strtolower(pathinfo($unit['master'], PATHINFO_EXTENSION)), $remote, $staging);
        $loresKey = $masterKey === null
            ? null
            : $this->stageRemote($event, $unit['lores'], strtolower(pathinfo($unit['lores'], PATHINFO_EXTENSION)), $remote, $staging);

        if ($masterKey === null || $loresKey === null) {
            return 'failed';
        }

        $posterKey = $unit['poster'] === null
            ? null
            : $this->stageRemote($event, $unit['poster'], strtolower(pathinfo($unit['poster'], PATHINFO_EXTENSION)), $remote, $staging);

        return $this->ingestor->ingestPreprocessedVideo(
            $event, $photographerId, $masterKey, $loresKey, $posterKey, $unit['master'],
        ) !== null ? 'imported' : 'skipped';
    }

    /**
     * Egy távoli fájl letöltése a lokális stagingbe. Null hiba esetén.
     */
    private function stageRemote(
        Event $event,
        string $path,
        string $extension,
        Filesystem $remote,
        Filesystem $staging,
    ): ?string {
        $key = sprintf('originals/%d/%s.%s', $event->id, (string) Str::uuid(), $extension);

        try {
            $stream = $remote->readStream($path);

            if (! is_resource($stream)) {
                return null;
            }

            $staging->writeStream($key, $stream);
            fclose($stream);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return $key;
    }

    private function isDirectory(Filesystem $disk, string $path): bool
    {
        if (rescue(fn (): bool => $disk->fileExists($path), false, false)) {
            return false;
        }

        return rescue(fn (): bool => $disk->directoryExists($path), false, false)
            || rescue(fn (): bool => count($disk->allFiles($path)) > 0 || count($disk->directories($path)) > 0, false, false);
    }

    /** A könyvtár-rész egy útvonalból (a `.`-t üresre normalizálva). */
    private function dirOf(string $path): string
    {
        $dir = trim(str_replace('\\', '/', dirname($path)), '/.');

        return $dir === '' ? '.' : $dir;
    }

    /**
     * Útvonal-normalizálás + traversal-védelem (`..` tiltva, root-relatív marad).
     */
    private function normalize(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Érvénytelen útvonal.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function breadcrumb(string $path): array
    {
        $out = [];
        $acc = '';

        foreach (array_filter(explode('/', $path)) as $segment) {
            $acc = $acc === '' ? $segment : "{$acc}/{$segment}";
            $out[] = ['name' => $segment, 'path' => $acc];
        }

        return $out;
    }

    private function safeSize(Filesystem $disk, string $file): ?int
    {
        try {
            return $disk->size($file);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFileCount(Filesystem $disk, string $dir): ?int
    {
        try {
            return count($disk->allFiles($dir));
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeLastModified(Filesystem $disk, string $file): ?string
    {
        try {
            return CarbonImmutable::createFromTimestamp($disk->lastModified($file))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
