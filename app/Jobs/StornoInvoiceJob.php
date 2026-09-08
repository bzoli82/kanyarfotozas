<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Invoicing\InvoiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Egy kiállított számla sztornózása — teljes visszatérítéskor automatikusan indul.
 */
class StornoInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 600, 3600];

    public function __construct(public int $invoiceId) {}

    public function handle(InvoiceManager $invoices): void
    {
        $invoice = Invoice::with('order')->find($this->invoiceId);

        if (! $invoice || ! $invoice->isIssued() || $invoice->isStorno()) {
            return;
        }

        try {
            $invoices->stornoFor($invoice);
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
