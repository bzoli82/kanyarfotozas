<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\InvoiceManager;
use App\Services\Payments\PaymentGatewayManager;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Kosar oldal (/cart) — a tenyleges tartalom kliens oldalon, Pinia +
     * localStorage-ban el, a Cart/Index.vue a betoltodeskor lekeri az aktualis
     * arakat/allapotot a /api/cart vegponttol (nehogy elavult ar latszodjon).
     */
    public function index(PaymentGatewayManager $gateways, InvoiceManager $invoices): Response
    {
        return Inertia::render('Cart/Index', [
            'paymentProviders' => $gateways->options(),
            // Ha van számlázó beállítva, be kell kérni a vevő nevét + országát.
            'billingRequired' => $invoices->isConfigured(),
        ]);
    }
}
