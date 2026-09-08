<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Invoicing\InvoiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * A sikeres fizetés után (ha a számlázás be van kapcsolva) kiállíttatja a
 * végszámlát. Hiba esetén újrapróbál; végleges hiba után a rendelés
 * részletnézetén „Számla kiállítása" gombbal kézzel is indítható.
 */
class IssueInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 600, 3600];

    public function __construct(public int $orderId) {}

    public function handle(InvoiceManager $invoices): void
    {
        if (! $invoices->isConfigured()) {
            return;
        }

        $order = Order::find($this->orderId);

        if (! $order || ! $order->isPaid()) {
            return;
        }

        try {
            $invoices->issueFor($order);
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
