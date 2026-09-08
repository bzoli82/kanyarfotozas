<?php

namespace App\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Egy tömeges média-import batch haladásának követése — a `job_batches` tábla
 * (job-szintű állapot) + cache-számlálók (fájl-szintű imported/skipped/failed).
 *
 * A batch neve: `event-import:{event_id}` — így eseményenként visszakereshető a
 * legutóbbi futás.
 */
class MediaImportProgress
{
    private const TTL_HOURS = 48;

    public static function batchName(int $eventId): string
    {
        return "event-import:{$eventId}";
    }

    /** A batch indításakor rögzíti az összes egység számát (a számlálók lazán nőnek). */
    public function start(string $batchId, int $total): void
    {
        Cache::put($this->key($batchId, 'total'), $total, now()->addHours(self::TTL_HOURS));
    }

    /** Egy chunk lefutása után növeli a számlálókat (`increment` hiányzó kulcsot is létrehoz). */
    public function record(string $batchId, int $imported, int $skipped, int $failed): void
    {
        foreach (['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed] as $suffix => $n) {
            if ($n > 0) {
                Cache::increment($this->key($batchId, $suffix), $n);
            }
        }
    }

    /**
     * Az esemény legutóbbi import-futásának állapota, vagy null ha sosem volt.
     *
     * @return array{
     *     running: bool,
     *     finished: bool,
     *     cancelled: bool,
     *     total: int,
     *     imported: int,
     *     skipped: int,
     *     failed: int,
     *     processed: int,
     *     finished_at: string|null
     * }|null
     */
    public function forEvent(int $eventId): ?array
    {
        $row = DB::table('job_batches')
            ->where('name', self::batchName($eventId))
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return null;
        }

        $batch = Bus::findBatch($row->id);
        $total = (int) Cache::get($this->key($row->id, 'total'), $row->total_jobs);
        $imported = (int) Cache::get($this->key($row->id, 'imported'), 0);
        $skipped = (int) Cache::get($this->key($row->id, 'skipped'), 0);
        $failed = (int) Cache::get($this->key($row->id, 'failed'), 0);

        $finished = $batch?->finished() ?? ($row->finished_at !== null);
        $cancelled = $batch?->cancelled() ?? ($row->cancelled_at !== null);

        return [
            'running' => ! $finished && ! $cancelled,
            'finished' => (bool) $finished,
            'cancelled' => (bool) $cancelled,
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'processed' => $imported + $skipped + $failed,
            'finished_at' => $row->finished_at ? now()->createFromTimestamp($row->finished_at)->toIso8601String() : null,
        ];
    }

    private function key(string $batchId, string $suffix): string
    {
        return "media-import:{$batchId}:{$suffix}";
    }
}
