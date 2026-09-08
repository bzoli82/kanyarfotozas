<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\StripeCheckoutGateway;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidArgumentException as StripeInvalidArgumentException;
use Throwable;

/**
 * A meglévő StripeCheckoutGateway (hosztolt Checkout) adaptere a közös
 * PaymentGateway felülethez. A Stripe-specifikus sikeres-oldal + webhook
 * továbbra is közvetlenül a StripeCheckoutGateway-t használja.
 */
class StripePaymentGateway implements PaymentGateway
{
    public function __construct(private StripeCheckoutGateway $stripe) {}

    public function provider(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function startPayment(Order $order): PaymentStartResult
    {
        try {
            $session = $this->stripe->createSession($order);
        } catch (ApiErrorException|StripeInvalidArgumentException $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        }

        return new PaymentStartResult(
            redirectUrl: (string) $session->url,
            reference: (string) $session->id,
        );
    }

    public function refund(Order $order, int $amountCents): PaymentRefundResult
    {
        try {
            $session = $this->stripe->retrieveSession((string) $order->payment_provider_reference);
            $paymentIntent = is_string($session->payment_intent) ? $session->payment_intent : ($session->payment_intent->id ?? null);

            if (blank($paymentIntent)) {
                throw new PaymentException('A Stripe fizetéshez nem tartozik payment intent — nem téríthető vissza.');
            }

            $refund = $this->stripe->createRefund($paymentIntent, $amountCents);
        } catch (PaymentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        }

        return new PaymentRefundResult((string) $refund->id);
    }
}
