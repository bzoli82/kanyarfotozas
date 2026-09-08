<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kézi kép-import a távoli fájlszerverről (a projekt SFTP `nas` diskje — a
 * felhasználói szóhasználatban „FTP szerver"). A fotósok a teljes méretű
 * eredetiket feltölthetik közvetlenül a szerverre; az admin az esemény
 * részletnézetében kiválasztja a mappát/fájlokat, és a rendszer letölti őket a
 * lokális stagingbe, majd a meglévő `ProcessImageMedia` pipeline legyártja a
 * thumbnailt + vízjeles előnézetet + letölthető változatokat, végül visszaarchivál.
 */
class FtpImport
{
    /** A távoli forrás diskje — ugyanaz az SFTP kapcsolat, amit az archiválás használ. */
    public const DISK = 'nas';

    /** Egy import-hívásban feldolgozott fájlok felső korlátja. */
    public const MAX_PER_IMPORT = 300;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private NasConnection $nas, private MediaIngestor $ingestor) {}

    public function isAvailable(): bool
    {
        return $this->nas->isConfigured();
    }

    /**
     * Egy távoli könyvtár tartalma: almappák + képfájlok.
     *
     * @return array{
     *     path: string,
     *     segments: list<array{name: string, path: string}>,
     *     directories: list<array{name: string, path: string}>,
     *     files: list<array{name: string, path: string, size: int|null, last_modified: string|null}>
     * }
     */
    public function browse(string $path = ''): array
    {
        $path = $this->normalize($path);
        $this->nas->applyRuntimeConfig();
        $disk = Storage::disk(self::DISK);

        $directories = collect($disk->directories($path))
            ->map(fn (string $dir): array => ['name' => basename($dir), 'path' => $this->normalize($dir)])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $files = collect($disk->files($path))
            ->filter(fn (string $file): bool => in_array(
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                self::IMAGE_EXTENSIONS,
                true,
            ))
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
     * A megadott távoli képfájlokat letölti a stagingbe, `Media` rekordot hoz
     * létre és elindítja a feldolgozó pipeline-t. Kétszeres importálást kiszűr:
     * ugyanaz a forrás-útvonal VAGY ugyanaz a fájltartalom (SHA-256) egy
     * eseményben csak egyszer kerül be (`skipped`-be számít).
     *
     * @param  list<string>  $paths
     * @return array{imported: int, skipped: int, failed: list<string>}
     */
    public function import(Event $event, array $paths, string $photographerId): array
    {
        $this->nas->applyRuntimeConfig();
        $remote = Storage::disk(self::DISK);
        $staging = Storage::disk(MediaStorage::STAGING);

        $imported = 0;
        $skipped = 0;
        $failed = [];

        foreach (array_slice(array_values($paths), 0, self::MAX_PER_IMPORT) as $rawPath) {
            try {
                $path = $this->normalize($rawPath);
            } catch (\InvalidArgumentException) {
                $failed[] = $rawPath;

                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $failed[] = $path;

                continue;
            }

            // Gyors elő-szűrés: ugyanezt a forrás-útvonalat már importáltuk.
            if (Media::query()->where('event_id', $event->id)->where('import_source_path', $path)->exists()) {
                $skipped++;

                continue;
            }

            $key = sprintf('originals/%d/%s.%s', $event->id, (string) Str::uuid(), $extension);

            try {
                $stream = $remote->readStream($path);

                if (! is_resource($stream)) {
                    $failed[] = $path;

                    continue;
                }

                $staging->writeStream($key, $stream);
                fclose($stream);
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $path;

                continue;
            }

            // Tartalom-alapú duplikátum-szűrés + rekord + pipeline egy helyen.
            if ($this->ingestor->ingestStaged($event, $photographerId, $key, Media::TYPE_PHOTO, $path) !== null) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed];
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

    private function safeLastModified(Filesystem $disk, string $file): ?string
    {
        try {
            return CarbonImmutable::createFromTimestamp($disk->lastModified($file))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
