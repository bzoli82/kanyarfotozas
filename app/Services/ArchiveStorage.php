<?php

namespace App\Services;

use App\Jobs\SyncArchiveMediaChunk;
use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Az ARCHÍV tároló futásidejű választása + a két tároló (saját SFTP/NAS ↔
 * Cloudflare R2 privát bucket) közti fájl-szinkron.
 *
 * Az archív rétegen a nagy fájlok élnek: a feltöltött eredeti + a megvásárolt
 * letölthető JPEG/WebP/MP4. A `MediaStorage::archive()` a
 * `config('media.disks.archive')` értéket adja; ezt a szolgáltatás
 * `applyRuntimeConfig()`-ja írja felül a `site_settings`-ben tárolt választással
 * (`media_archive_disk`), ha a superadmin a /admin/settings/storage oldalon
 * átállította. Üres beállítás = az `.env` (MEDIA_ARCHIVE_DISK) dönt.
 *
 * FONTOS: a `Media.original_storage = 'nas'` sorok az archív rétegre mutatnak
 * (a `'nas'` érték történeti — „az archív diskon van" a jelentése). A disk-váltás
 * után MINDET az új diskon keresi a letöltés, ezért VÁLTÁS ELŐTT le kell futtatni
 * a szinkront (`startSync()`), hogy a fájlok átkerüljenek.
 */
class ArchiveStorage
{
    /** A felületről választható archív diskek (a `local` szándékosan kimarad — azt az .env dönti). */
    public const CHOICES = ['nas', 'r2_private'];

    public const BATCH_NAME = 'archive-sync';

    private const CHUNK = 250;

    private const PROGRESS_TTL_HOURS = 48;

    // =====================================================================
    // A választás (site_settings)
    // =====================================================================

    /** A `site_settings`-ben tárolt nyers választás, vagy null (= az .env dönt). */
    public function configuredDisk(): ?string
    {
        $value = (string) SiteSetting::get('media_archive_disk', '');

        return in_array($value, self::CHOICES, true) ? $value : null;
    }

    /** Az EFFEKTÍV archív disk: a beállítás, vagy a config (.env) fallback. */
    public function disk(): string
    {
        return $this->configuredDisk() ?? (string) config('media.disks.archive', 'nas');
    }

    public function setDisk(?string $disk): void
    {
        SiteSetting::set('media_archive_disk', in_array($disk, self::CHOICES, true) ? $disk : null);

        $this->applyRuntimeConfig();
    }

    /**
     * A választást a config-ba tölti. Az AppServiceProvider::boot()-ból fut
     * (web + queue worker + cron egységesen). Ha nincs választás, nem nyúl a
     * config-hoz — így az .env marad érvényben.
     */
    public function applyRuntimeConfig(): void
    {
        if ($configured = $this->configuredDisk()) {
            config(['media.disks.archive' => $configured]);
        }
    }

    public function diskAvailable(string $disk): bool
    {
        return match ($disk) {
            'nas' => app(NasConnection::class)->isConfigured(),
            'r2_private' => app(R2Storage::class)->isConfigured(),
            default => false,
        };
    }

    /**
     * @return list<array{value: string, label: string, available: bool}>
     */
    public function options(): array
    {
        return [
            ['value' => 'nas', 'label' => 'Saját NAS / SFTP szerver', 'available' => $this->diskAvailable('nas')],
            ['value' => 'r2_private', 'label' => 'Cloudflare R2 (privát bucket)', 'available' => $this->diskAvailable('r2_private')],
        ];
    }

    // =====================================================================
    // Szinkron: NAS <-> R2
    // =====================================================================

    /**
     * Becslés a felülethez: hány média van az archív rétegen, hány a webhostingon.
     *
     * @return array{media_on_archive: int, media_on_local: int, files_estimate: int}
     */
    public function plan(): array
    {
        $onArchive = Media::query()->where('original_storage', Media::STORAGE_NAS)->count();

        return [
            'media_on_archive' => $onArchive,
            'media_on_local' => Media::query()->where('original_storage', Media::STORAGE_LOCAL)->count(),
            'files_estimate' => $onArchive * count(Media::ARCHIVABLE_FIELDS),
        ];
    }

    /**
     * Egy köteg média archív fájljainak másolása `$from` -> `$to` disk közt.
     * Idempotens: a célon már meglévő, azonos méretű fájlt kihagyja (kivéve `$force`).
     *
     * @param  list<int>  $mediaIds
     * @return array{copied: int, skipped: int, failed: int}
     */
    public function copyChunk(array $mediaIds, string $from, string $to, bool $force = false): array
    {
        $this->prepareDisk($from);
        $this->prepareDisk($to);

        $source = Storage::disk($from);
        $target = Storage::disk($to);

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        Media::query()->whereIn('id', $mediaIds)->get()->each(function (Media $media) use ($source, $target, $force, &$copied, &$skipped, &$failed): void {
            foreach (array_keys(Media::ARCHIVABLE_FIELDS) as $field) {
                $key = $media->{$field};

                if (blank($key)) {
                    continue;
                }

                try {
                    if (! $source->exists($key)) {
                        continue;
                    }

                    if (! $force && $target->exists($key) && $target->size($key) === $source->size($key)) {
                        $skipped++;

                        continue;
                    }

                    $stream = $source->readStream($key);
                    $target->writeStream($key, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $copied++;
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                }
            }
        });

        return compact('copied', 'skipped', 'failed');
    }

    /**
     * Elindít egy háttér-batch-et minden archivált média fájljainak másolására.
     * `null`, ha nincs mit másolni.
     */
    public function startSync(string $from, string $to): ?string
    {
        $ids = Media::query()->where('original_storage', Media::STORAGE_NAS)->pluck('id')->all();

        if ($ids === []) {
            return null;
        }

        $jobs = collect($ids)
            ->chunk(self::CHUNK)
            ->map(fn ($chunk) => new SyncArchiveMediaChunk($chunk->values()->all(), $from, $to))
            ->all();

        $batch = Bus::batch($jobs)
            ->name(self::BATCH_NAME)
            ->onQueue('imports')
            ->allowFailures()
            ->dispatch();

        Cache::put($this->progressKey($batch->id, 'total'), count($ids), now()->addHours(self::PROGRESS_TTL_HOURS));
        Cache::put($this->progressKey($batch->id, 'direction'), $this->directionLabel($from, $to), now()->addHours(self::PROGRESS_TTL_HOURS));

        return $batch->id;
    }

    public function recordProgress(string $batchId, int $copied, int $skipped, int $failed): void
    {
        foreach (['copied' => $copied, 'skipped' => $skipped, 'failed' => $failed] as $suffix => $count) {
            if ($count > 0) {
                Cache::increment($this->progressKey($batchId, $suffix), $count);
            }
        }
    }

    /**
     * A legutóbbi szinkron-batch állapota, vagy null ha sosem futott.
     *
     * @return array{running: bool, finished: bool, cancelled: bool, direction: string, total: int, copied: int, skipped: int, failed: int, progress: int, finished_at: string|null}|null
     */
    public function syncStatus(): ?array
    {
        $row = DB::table('job_batches')
            ->where('name', self::BATCH_NAME)
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return null;
        }

        $batch = Bus::findBatch($row->id);
        $finished = $batch?->finished() ?? ($row->finished_at !== null);
        $cancelled = $batch?->cancelled() ?? ($row->cancelled_at !== null);

        return [
            'running' => ! $finished && ! $cancelled,
            'finished' => (bool) $finished,
            'cancelled' => (bool) $cancelled,
            'direction' => (string) Cache::get($this->progressKey($row->id, 'direction'), ''),
            'total' => (int) Cache::get($this->progressKey($row->id, 'total'), $row->total_jobs),
            'copied' => (int) Cache::get($this->progressKey($row->id, 'copied'), 0),
            'skipped' => (int) Cache::get($this->progressKey($row->id, 'skipped'), 0),
            'failed' => (int) Cache::get($this->progressKey($row->id, 'failed'), 0),
            'progress' => $batch ? $batch->progress() : ($finished ? 100 : 0),
            'finished_at' => $row->finished_at ? now()->createFromTimestamp($row->finished_at)->toIso8601String() : null,
        ];
    }

    private function directionLabel(string $from, string $to): string
    {
        $name = fn (string $disk): string => $disk === 'nas' ? 'NAS' : 'R2';

        return $name($from).' → '.$name($to);
    }

    private function prepareDisk(string $disk): void
    {
        if ($disk === 'nas') {
            app(NasConnection::class)->applyRuntimeConfig();
        }
    }

    private function progressKey(string $batchId, string $suffix): string
    {
        return "archive-sync:{$batchId}:{$suffix}";
    }
}
