<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\Order;

/**
 * Nincs számlázó beállítva — minden hívás egyértelmű hibát dob (a hívó ellenőrzi
 * az `isConfigured()`-et, így normál esetben ide nem jutunk el).
 */
class NullInvoiceProvider implements InvoiceProvider
{
    public function provider(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function issue(Order $order): IssuedInvoice
    {
        throw new InvoiceException('Nincs beállítva számlázó szolgáltató.');
    }

    public function storno(Invoice $invoice): IssuedInvoice
    {
        throw new InvoiceException('Nincs beállítva számlázó szolgáltató.');
    }
}
