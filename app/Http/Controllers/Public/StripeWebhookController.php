<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\StripeCheckoutGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    /**
     * Stripe webhook vegpont — a sikeres-oldal szinkron ellenorzese mellett ez
     * a biztos/aszinkron forras a fizetes lezarasara (pl. ha a vasarlo bezarja
     * a bongeszot mielott visszairanyitana a sikeres oldalra).
     */
    public function handle(Request $request, StripeCheckoutGateway $stripe, CheckoutService $checkout): Response
    {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (\Throwable) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $order = Order::query()->where('payment_provider_reference', $session->id)->first();

            if ($order) {
                $checkout->markPaid($order);
            }
        }

        return response('ok');
    }
}
