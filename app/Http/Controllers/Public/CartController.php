<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Order;
use App\Services\Invoicing\InvoiceManager;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Kosar oldal (/cart) — a tenyleges tartalom kliens oldalon, Pinia +
     * localStorage-ban el, a Cart/Index.vue a betoltodeskor lekeri az aktualis
     * arakat/allapotot a /api/cart vegponttol (nehogy elavult ar latszodjon).
     *
     * Elhagyott-kosar emlekezteto: `?order={id}` + ervenyes alairas eseten a
     * rendeles meg elerheto tetelei visszakerulnek a kosarba (`resumeItems` prop).
     */
    public function index(Request $request, PaymentGatewayManager $gateways, InvoiceManager $invoices): Response
    {
        return Inertia::render('Cart/Index', [
            'paymentProviders' => $gateways->options(),
            // Ha van számlázó beállítva, be kell kérni a vevő nevét + országát.
            'billingRequired' => $invoices->isConfigured(),
            'resumeItems' => $this->resumeItems($request),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resumeItems(Request $request): array
    {
        if (! $request->hasValidSignature() || ! $request->filled('order')) {
            return [];
        }

        $order = Order::query()
            ->where('payment_status', Order::STATUS_PENDING)
            ->find($request->integer('order'));

        if (! $order) {
            return [];
        }

        return $order->media()
            ->where('status', Media::STATUS_READY)
            ->with('event:id,name,slug')
            ->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'price_cents' => (int) $m->price_cents,
                'thumbnail_s3_key' => $m->thumbnail_s3_key,
                'event_name' => $m->event?->name ?? '',
                'event_id' => $m->event?->id,
                'event_slug' => $m->event?->slug,
            ])
            ->values()
            ->all();
    }
}
