<?php

namespace App\Jobs;

use App\Services\ArchiveStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Egy archív-szinkron batch chunkja: N média eredeti + letölthető fájljainak
 * másolása a `$from` disk-ről a `$to` disk-re (App\Services\ArchiveStorage).
 *
 * A batch-et a StorageSettingsController::startArchiveSync állítja össze; a
 * folyamat a /admin/settings/storage oldalon látszik.
 */
class SyncArchiveMediaChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  list<int>  $mediaIds
     */
    public function __construct(
        public array $mediaIds,
        public string $from,
        public string $to,
    ) {}

    public function handle(ArchiveStorage $archive): void
    {
        if ($this->batch()?->cancelled() || $this->mediaIds === []) {
            return;
        }

        $result = $archive->copyChunk($this->mediaIds, $this->from, $this->to);

        $archive->recordProgress($this->batch()->id, $result['copied'], $result['skipped'], $result['failed']);
    }
}
