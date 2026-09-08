<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\FtpImport;
use App\Services\MediaImportProgress;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Egy tömeges import-batch egy chunkja: N „egység" (kép vagy elő-feldolgozott
 * videó-hármas) beolvasása a forrás-tárolóból (App\Services\FtpImport).
 *
 * A batch-et az App\Http\Controllers\Admin\MediaImportController::store állítja
 * össze; a haladást az App\Services\MediaImportProgress cache-számlálói követik.
 */
class ImportMediaChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [30, 120];

    /**
     * @param  list<array<string, mixed>>  $units  FtpImport::planImport() egységei
     */
    public function __construct(
        public int $eventId,
        public string $photographerId,
        public array $units,
    ) {
        $this->onQueue('imports');
    }

    public function handle(FtpImport $import, MediaImportProgress $progress): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $event = Event::find($this->eventId);

        if (! $event) {
            return;
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->units as $unit) {
            try {
                $outcome = $import->importUnit($event, $unit, $this->photographerId);
            } catch (Throwable $e) {
                report($e);
                $outcome = 'failed';
            }

            match ($outcome) {
                'imported' => $imported++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        if ($this->batch()) {
            $progress->record($this->batch()->id, $imported, $skipped, $failed);
        }
    }
}
