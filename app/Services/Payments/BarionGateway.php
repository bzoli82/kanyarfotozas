<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\SiteBranding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Barion Smart Gateway v2 fizetési átjáró — hosztolt kártyás fizetés.
 *
 * Folyamat:
 *   1. startPayment()  -> POST {base}/v2/Payment/Start (JSON, `POSKey` a testben),
 *      a válasz `GatewayUrl`-jére irányítjuk a vásárlót.
 *   2. A vásárló visszatér a `RedirectUrl`-re (GET, `?paymentId=`) —
 *      CheckoutController::barionReturn kezeli (élmény, NEM megbízható forrás).
 *   3. Callback (szerver-szerver POST a `CallbackUrl`-re, `paymentId` mezővel) —
 *      a BarionCallbackController a MEGBÍZHATÓ forrás: minden esetben visszakérdez
 *      a GetPaymentState végponton (a POSKey a mi titkunk → a válasz hiteles),
 *      és `Succeeded` állapotnál zárja le a rendelést.
 *
 * A Barion NEM használ HMAC-aláírást: a biztonság alapja, hogy a POSKey titkos,
 * és a fizetés állapotát MINDIG a saját szerverünkről kérdezzük vissza.
 */
class BarionGateway implements PaymentGateway
{
    private const SANDBOX_BASE = 'https://api.test.barion.com';

    private const LIVE_BASE = 'https://api.barion.com';

    private const SANDBOX_GATEWAY = 'https://secure.test.barion.com';

    private const LIVE_GATEWAY = 'https://secure.barion.com';

    public function provider(): string
    {
        return 'barion';
    }

    public function isConfigured(): bool
    {
        return filled($this->posKey()) && filled($this->payee());
    }

    public function startPayment(Order $order): PaymentStartResult
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('A Barion nincs beállítva (POSKey / kifizetési e-mail hiányzik).');
        }

        $brand = app(SiteBranding::class)->name();
        $total = (int) $order->total_cents;
        $paymentRequestId = $this->paymentRequestId($order);

        $body = [
            'POSKey' => $this->posKey(),
            'PaymentType' => 'Immediate',
            'GuestCheckout' => true,
            'FundingSources' => ['All'],
            'PaymentRequestId' => $paymentRequestId,
            'PayerHint' => $order->buyer_email,
            'Currency' => 'HUF',
            'Locale' => 'hu-HU',
            'RedirectUrl' => route('public.checkout.barion.return'),
            'CallbackUrl' => route('api.barion.callback'),
            'Transactions' => [[
                'POSTransactionId' => 'order-'.$order->id,
                'Payee' => $this->payee(),
                'Total' => $total,
                'Comment' => $brand.' rendelés '.$order->order_number,
                'Items' => [[
                    'Name' => Str::limit($brand.' — fotó/videó letöltés', 250),
                    'Description' => 'Rendelés '.$order->order_number,
                    'Quantity' => 1,
                    'Unit' => 'db',
                    'UnitPrice' => $total,
                    'ItemTotal' => $total,
                ]],
            ]],
        ];

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/v2/Payment/Start', $body);

        $json = $response->json();

        if (! $response->successful() || ! empty($json['Errors'])) {
            throw new PaymentException('Barion indítási hiba'.$this->errorSuffix($json).'.');
        }

        $paymentId = (string) ($json['PaymentId'] ?? '');
        $gatewayUrl = (string) ($json['GatewayUrl'] ?? '');

        if (blank($paymentId)) {
            throw new PaymentException('Barion válasz hiányos (nincs PaymentId).');
        }

        if (blank($gatewayUrl)) {
            $gatewayUrl = $this->gatewayUrl().'/Pay?Id='.$paymentId;
        }

        return new PaymentStartResult(
            redirectUrl: $gatewayUrl,
            reference: $paymentId,
            providerOrderRef: $paymentRequestId,
        );
    }

    public function refund(Order $order, int $amountCents): PaymentRefundResult
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('A Barion nincs beállítva.');
        }

        $paymentId = (string) $order->payment_provider_reference;
        $state = $this->paymentState($paymentId);
        $transaction = collect($state['Transactions'] ?? [])
            ->first(fn ($t) => ($t['TransactionType'] ?? null) === 'CardProcessed' || filled($t['TransactionId'] ?? null));

        if (blank($transaction['TransactionId'] ?? null)) {
            throw new PaymentException('A Barion fizetéshez nem található tranzakció — nem téríthető vissza.');
        }

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/v2/Payment/Refund', [
                'POSKey' => $this->posKey(),
                'PaymentId' => $paymentId,
                'TransactionsToRefund' => [[
                    'TransactionId' => $transaction['TransactionId'],
                    'POSTransactionId' => 'order-'.$order->id,
                    'AmountToRefund' => $amountCents,
                ]],
            ]);

        $json = $response->json();

        if (! $response->successful() || ! empty($json['Errors'])) {
            throw new PaymentException('Barion visszatérítési hiba'.$this->errorSuffix($json).'.');
        }

        return new PaymentRefundResult((string) ($json['RefundedTransactions'][0]['TransactionId'] ?? $paymentId));
    }

    /**
     * A fizetés hiteles állapota a Barion szerveréről (a callback + a visszatérés is ezt hívja).
     *
     * @return array<string, mixed>
     */
    public function paymentState(string $paymentId): array
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get($this->baseUrl().'/v2/Payment/GetPaymentState', [
                'POSKey' => $this->posKey(),
                'PaymentId' => $paymentId,
            ]);

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function isSucceeded(string $paymentId): bool
    {
        return ($this->paymentState($paymentId)['Status'] ?? null) === 'Succeeded';
    }

    private function paymentRequestId(Order $order): string
    {
        $prefix = Str::of(app(SiteBranding::class)->slug())->upper()->substr(0, 4)->padRight(2, 'X');

        return $prefix.'-'.$order->id.'-'.Str::upper(Str::random(8));
    }

    public function orderIdFromRequestId(?string $requestId): ?int
    {
        if (blank($requestId) || ! preg_match('/^[A-Z0-9]+-(\d+)-/', $requestId, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function errorSuffix(?array $json): string
    {
        $errors = collect($json['Errors'] ?? [])
            ->map(fn ($e) => is_array($e) ? ($e['Title'] ?? $e['ErrorCode'] ?? '') : (string) $e)
            ->filter()
            ->implode(', ');

        return filled($errors) ? ' ('.$errors.')' : '';
    }

    private function baseUrl(): string
    {
        return $this->sandbox() ? self::SANDBOX_BASE : self::LIVE_BASE;
    }

    private function gatewayUrl(): string
    {
        return $this->sandbox() ? self::SANDBOX_GATEWAY : self::LIVE_GATEWAY;
    }

    private function sandbox(): bool
    {
        return (bool) config('services.barion.sandbox', true);
    }

    private function posKey(): string
    {
        return (string) config('services.barion.pos_key');
    }

    private function payee(): string
    {
        return (string) config('services.barion.payee');
    }
}
