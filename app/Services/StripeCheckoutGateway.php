<?php

namespace App\Services;

use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Vekony wrapper a Stripe PHP SDK korul — kulon osztaly, hogy a CheckoutController
 * es a webhook tesztelheto legyen valodi Stripe API-hivas nelkul (a tesztekben
 * ez az osztaly mockolhato a konteneren keresztul).
 */
class StripeCheckoutGateway
{
    private ?StripeClient $client = null;

    /**
     * Lusta peldanyositas — ha itt a konstruktorban hoznank letre a StripeClient-et,
     * az konfiguralatlan (ures) API-kulcs eseten AZONNAL dobna kivetelt, meg azelott,
     * hogy egy controller-metodus (pl. csak validalast vegzo) egyaltalan lefutna —
     * a Laravel ugyanis a route-metodus osszes tipizalt fuggosseget felold(-tatja)
     * a konteneren keresztul, mielott a metodus torzse lefutna.
     */
    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    public function createSession(Order $order): Session
    {
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->buyer_email,
            'line_items' => [[
                'price_data' => [
                    // HUF a Stripe "zero-decimal" penznemei kozott van — a unit_amount
                    // itt kozvetlenul a teljes forint-osszeg, NEM szorozva 100-zal.
                    'currency' => 'huf',
                    'product_data' => [
                        'name' => app(SiteBranding::class)->name()." rendelés — {$order->media()->count()} média",
                    ],
                    'unit_amount' => $order->total_cents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('public.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('public.checkout.cancel'),
            'metadata' => ['order_id' => (string) $order->id],
        ]);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Visszatérítés a payment intent alapján (`$amountCents` = forint, HUF zero-decimal).
     */
    public function createRefund(string $paymentIntent, int $amountCents): Refund
    {
        return $this->client()->refunds->create([
            'payment_intent' => $paymentIntent,
            'amount' => $amountCents,
        ]);
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent($payload, $signatureHeader, (string) config('services.stripe.webhook_secret'));
    }
}
