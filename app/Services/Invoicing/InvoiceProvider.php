<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\Order;

/**
 * Számlázó szolgáltató közös felülete (jelenleg Billingo v3). A tényleges
 * NAV-jelentést a szolgáltató végzi.
 */
interface InvoiceProvider
{
    public function provider(): string;

    public function isConfigured(): bool;

    /**
     * Végszámla kiállítása a rendeléshez (a vevő `billing_*` adataiból).
     *
     * @throws InvoiceException
     */
    public function issue(Order $order): IssuedInvoice;

    /**
     * A megadott számla sztornózása.
     *
     * @throws InvoiceException
     */
    public function storno(Invoice $invoice): IssuedInvoice;
}
