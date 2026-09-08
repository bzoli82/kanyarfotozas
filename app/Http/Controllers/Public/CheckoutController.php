<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\Invoicing\InvoiceManager;
use App\Services\Payments\BarionGateway;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SimplePayGateway;
use App\Services\StripeCheckoutGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckoutController extends Controller
{
    /**
     * Kosarbol rendelest + a valasztott szolgaltatonal fizetest hoz letre, es a
     * redirect URL-t adja vissza — a kliens oldal (Cart/Index.vue) erre navigal at.
     */
    public function store(Request $request, CheckoutService $checkout, PaymentGatewayManager $gateways, InvoiceManager $invoices): JsonResponse
    {
        // Ha a számlázás be van kapcsolva, magyar előírás szerint kell a vevő neve + országa.
        $needsBilling = $invoices->isConfigured();

        $data = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer'],
            'email' => ['required', 'email'],
            'coupon_code' => ['nullable', 'string'],
            'plate_consent' => ['sometimes', 'boolean'],
            'terms_accepted' => ['accepted'],
            'provider' => ['sometimes', 'nullable', Rule::in(['stripe', 'simplepay', 'barion'])],
            'billing_name' => [Rule::requiredIf($needsBilling), 'nullable', 'string', 'max:255'],
            'billing_country' => [Rule::requiredIf($needsBilling), 'nullable', 'string', 'size:2'],
            'billing_zip' => ['nullable', 'string', 'max:20'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_tax_number' => ['nullable', 'string', 'max:30'],
        ]);

        $available = $gateways->available();
        $gateway = filled($data['provider'] ?? null) && isset($available[$data['provider']])
            ? $available[$data['provider']]
            : $gateways->default();

        $order = $checkout->createPendingOrder(
            $data['media_ids'],
            $data['email'],
            $data['coupon_code'] ?? null,
            (bool) ($data['plate_consent'] ?? false),
            $data,
        );

        try {
            $result = $gateway->startPayment($order);
        } catch (PaymentException $e) {
            Log::error("Fizetés indítása sikertelen (order #{$order->id}, {$gateway->provider()}): {$e->getMessage()}");

            return response()->json([
                'message' => 'A fizetés jelenleg nem elérhető. Kérjük, próbáld újra később, vagy keress minket.',
            ], 502);
        }

        $checkout->attachPaymentReference($order, $gateway->provider(), $result->reference, $result->providerOrderRef);

        return response()->json(['redirect_url' => $result->redirectUrl]);
    }

    /**
     * Stripe sikeres-oldal. A webhook a fizetes tenyleges lezarasa (aszinkron,
     * biztos forras), de itt szinkron modon is ellenorizzuk a session statuszat.
     */
    public function success(Request $request, StripeCheckoutGateway $stripe, CheckoutService $checkout): InertiaResponse
    {
        $sessionId = (string) $request->query('session_id');
        $order = Order::query()->where('payment_provider_reference', $sessionId)->firstOrFail();

        if (! $order->isPaid()) {
            $session = $stripe->retrieveSession($sessionId);

            if ($session->payment_status === 'paid') {
                $checkout->markPaid($order);
                $order->refresh();
            }
        }

        return $this->renderResult($order);
    }

    /**
     * SimplePay visszateres (a vasarlo bongeszoje). NEM megbizhato forras — a
     * fizetest az IPN zarja le; itt csak azonnali visszajelzest adunk. Az `r`
     * aláirását ellenőrizzük, hogy hamisitott URL-t ne fogadjunk el.
     */
    public function simplePayReturn(Request $request, SimplePayGateway $simplePay, CheckoutService $checkout): InertiaResponse|RedirectResponse
    {
        $r = (string) $request->query('r');
        $signature = $request->query('signature');

        if (blank($r) || ! $simplePay->verifySignature($r, is_string($signature) ? $signature : null)) {
            return redirect()->route('public.checkout.cancel');
        }

        $payload = $simplePay->decodeReturn($r);
        $event = $payload['e'] ?? 'FAIL';

        $order = $this->findSimplePayOrder($simplePay, $payload);

        if (! $order) {
            return redirect()->route('public.checkout.cancel');
        }

        if ($event === 'SUCCESS') {
            // A vasarlo gyorsabban erhet ide, mint az IPN — zarjuk le itt is (idempotens).
            $checkout->markPaid($order);
            $order->refresh();

            return $this->renderResult($order);
        }

        return redirect()->route('public.checkout.cancel');
    }

    /**
     * Barion visszateres (a vasarlo bongeszoje). NEM megbizhato forras — a fizetest
     * a callback zarja le; itt csak a Barion szerveretol lekert allapotot mutatjuk.
     */
    public function barionReturn(Request $request, BarionGateway $barion, CheckoutService $checkout): InertiaResponse|RedirectResponse
    {
        $paymentId = (string) $request->query('paymentId');

        $order = filled($paymentId)
            ? Order::query()->where('payment_provider_reference', $paymentId)->where('payment_provider', 'barion')->first()
            : null;

        if (! $order) {
            return redirect()->route('public.checkout.cancel');
        }

        if (! $order->isPaid() && $barion->isSucceeded($paymentId)) {
            $checkout->markPaid($order);
            $order->refresh();
        }

        return $order->isPaid()
            ? $this->renderResult($order)
            : redirect()->route('public.checkout.cancel');
    }

    public function cancel(): InertiaResponse
    {
        return Inertia::render('Checkout/Cancel');
    }

    /**
     * @param  array{t?: int, o?: string}  $payload
     */
    private function findSimplePayOrder(SimplePayGateway $simplePay, array $payload): ?Order
    {
        $transactionId = (string) ($payload['t'] ?? '');

        if (filled($transactionId)) {
            $order = Order::query()->where('payment_provider_reference', $transactionId)->first();
            if ($order) {
                return $order;
            }
        }

        // Fallback: az orderRef-be kódolt rendelés-azonosító (KF-{id}-{rand}).
        $orderId = $simplePay->orderIdFromRef($payload['o'] ?? null);

        return $orderId ? Order::query()->where('id', $orderId)->where('payment_provider', 'simplepay')->first() : null;
    }

    private function renderResult(Order $order): InertiaResponse
    {
        return Inertia::render('Checkout/Success', [
            'paid' => $order->isPaid(),
            'orderNumber' => $order->order_number,
            'downloadUrl' => $order->isPaid() ? route('public.download.show', $order->download_token) : null,
        ]);
    }
}
