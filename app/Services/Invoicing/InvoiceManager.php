<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceSettings;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A számlázás vezénylője: feloldja a szolgáltatót (Billingo / Null), kiállíttatja
 * a számlát, és eltárolja a rekordot + a PDF-et a privát diskre.
 */
class InvoiceManager
{
    public function __construct(private InvoiceSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured();
    }

    public function provider(): InvoiceProvider
    {
        return match ($this->settings->provider()) {
            InvoiceSettings::PROVIDER_BILLINGO => app(BillingoInvoiceProvider::class),
            default => new NullInvoiceProvider,
        };
    }

    /**
     * Végszámla a rendeléshez. Idempotens: ha már van sikeres normál számla, azt adja vissza.
     */
    public function issueFor(Order $order): Invoice
    {
        $existing = $order->invoices()->where('type', Invoice::TYPE_NORMAL)->where('status', Invoice::STATUS_ISSUED)->first();
        if ($existing) {
            return $existing;
        }

        $record = $order->invoices()->where('type', Invoice::TYPE_NORMAL)->first()
            ?? $order->invoices()->make(['type' => Invoice::TYPE_NORMAL]);
        $record->provider = $this->settings->provider();
        $record->attempts++;

        try {
            $issued = $this->provider()->issue($order);
            $this->fill($record, $issued);
        } catch (Throwable $e) {
            $record->fill(['status' => Invoice::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            $order->invoices()->save($record);

            throw $e instanceof InvoiceException ? $e : new InvoiceException($e->getMessage(), previous: $e);
        }

        $order->invoices()->save($record);

        return $record;
    }

    /**
     * A megadott (kiállított, normál) számla sztornózása.
     */
    public function stornoFor(Invoice $invoice): Invoice
    {
        if (! $invoice->isIssued() || $invoice->isStorno()) {
            throw new InvoiceException('Csak kiállított, normál számla sztornózható.');
        }

        if ($invoice->order->invoices()->where('type', Invoice::TYPE_STORNO)->where('status', Invoice::STATUS_ISSUED)->exists()) {
            throw new InvoiceException('Ehhez a rendeléshez már van sztornó számla.');
        }

        $issued = $this->provider()->storno($invoice);

        $storno = $invoice->order->invoices()->make([
            'provider' => $this->settings->provider(),
            'type' => Invoice::TYPE_STORNO,
            'storno_of' => $invoice->id,
        ]);
        $this->fill($storno, $issued);
        $invoice->order->invoices()->save($storno);

        return $storno;
    }

    private function fill(Invoice $record, IssuedInvoice $issued): void
    {
        $record->fill([
            'external_id' => $issued->externalId,
            'number' => $issued->number,
            'gross_cents' => $issued->grossCents,
            'status' => Invoice::STATUS_ISSUED,
            'error' => null,
            'issued_at' => now(),
        ]);

        if ($issued->pdf !== null) {
            $path = "invoices/{$record->order_id}-".($record->type === Invoice::TYPE_STORNO ? 'storno-' : '').uniqid().'.pdf';
            Storage::disk(MediaStorage::STAGING)->put($path, $issued->pdf);
            $record->pdf_path = $path;
        }
    }
}
