<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\SiteBranding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SimplePay (OTP Mobil) v2 fizetési átjáró — hosztolt kártyás fizetés.
 *
 * Folyamat:
 *   1. startPayment()  -> POST {base}/payment/v2/start (HMAC-SHA384 aláírt JSON),
 *      a válasz `paymentUrl`-jére irányítjuk a vásárlót.
 *   2. A vásárló visszatér a `url`-re (GET, `r` + `signature` query) — a
 *      CheckoutController::simplePayReturn kezeli (élmény, nem megbízható forrás).
 *   3. IPN (szerver-szerver POST) — a SimplePayIpnController a MEGBÍZHATÓ forrás
 *      a fizetés lezárásához; a választ ugyanazzal az aláírással + `receiveDate`-tel
 *      kell visszaküldeni.
 *
 * Minden aláírás: base64( HMAC-SHA384( nyers-body, merchant secret key ) ).
 *
 * Megjegyzés: a `/start` `invoice` (számlázási adat) mezőt nem küldjük, mert az
 * oldal csak e-mailt kér — a SimplePay a saját fizetőoldalán gyűjti be. Ha a
 * SimplePay hiányzó számlázási adat miatt visszautasít, a kosárban be kell kérni
 * a nevet/címet és átadni a `startPayment()` `$billing` paraméterében.
 */
class SimplePayGateway implements PaymentGateway
{
    private const SANDBOX_BASE = 'https://sandbox.simplepay.hu';

    private const LIVE_BASE = 'https://secure.simplepay.hu';

    private const SDK_VERSION = 'SimplePayV2_PHP_1.0';

    public function provider(): string
    {
        return 'simplepay';
    }

    public function isConfigured(): bool
    {
        return filled($this->merchant()) && filled($this->secretKey());
    }

    /**
     * @param  array<string, mixed>  $billing  Opcionális `invoice` mezők (name, country, ...)
     */
    public function startPayment(Order $order, array $billing = []): PaymentStartResult
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('A SimplePay nincs beállítva (merchant / secret key hiányzik).');
        }

        $orderRef = $this->orderRef($order);

        $body = [
            'salt' => Str::random(32),
            'merchant' => $this->merchant(),
            'orderRef' => $orderRef,
            'currency' => 'HUF',
            'customerEmail' => $order->buyer_email,
            'language' => 'HU',
            'sdkVersion' => self::SDK_VERSION,
            'methods' => ['CARD'],
            'total' => (int) $order->total_cents,
            'timeout' => now()->addMinutes(30)->toIso8601String(),
            'url' => route('public.checkout.simplepay.return'),
        ];

        if ($billing !== []) {
            $body['invoice'] = $billing;
        }

        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Signature' => $this->sign($payload),
        ])->withBody($payload, 'application/json')
            ->timeout(30)
            ->post($this->baseUrl().'/payment/v2/start');

        if (! $response->successful()) {
            throw new PaymentException('SimplePay indítási hiba (HTTP '.$response->status().').');
        }

        $json = $response->json();

        if (! empty($json['errorCodes'])) {
            throw new PaymentException('SimplePay hibakód: '.implode(', ', (array) $json['errorCodes']));
        }

        if (blank($json['paymentUrl'] ?? null) || blank($json['transactionId'] ?? null)) {
            throw new PaymentException('SimplePay válasz hiányos (nincs paymentUrl / transactionId).');
        }

        return new PaymentStartResult(
            redirectUrl: (string) $json['paymentUrl'],
            reference: (string) $json['transactionId'],
            providerOrderRef: $orderRef,
        );
    }

    /**
     * Visszatérítés (teljes vagy részleges) — SimplePay v2 `/payment/v2/refund`.
     */
    public function refund(Order $order, int $amountCents): PaymentRefundResult
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('A SimplePay nincs beállítva.');
        }

        $body = [
            'salt' => Str::random(32),
            'merchant' => $this->merchant(),
            'orderRef' => (string) $order->payment_provider_order_ref,
            'transactionId' => (string) $order->payment_provider_reference,
            'currency' => 'HUF',
            'refundTotal' => $amountCents,
        ];

        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Signature' => $this->sign($payload),
        ])->withBody($payload, 'application/json')
            ->timeout(30)
            ->post($this->baseUrl().'/payment/v2/refund');

        $json = $response->json();

        if (! $response->successful() || ! empty($json['errorCodes'])) {
            throw new PaymentException('SimplePay visszatérítési hiba'.(! empty($json['errorCodes']) ? ' ('.implode(', ', (array) $json['errorCodes']).')' : '').'.');
        }

        return new PaymentRefundResult((string) ($json['transactionId'] ?? $order->payment_provider_reference));
    }

    /**
     * A visszatérési (`r` query) vagy IPN body aláírásának ellenőrzése.
     */
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (blank($signatureHeader)) {
            return false;
        }

        return hash_equals($this->sign($rawBody), trim($signatureHeader));
    }

    /**
     * A visszatérési `r` (base64 JSON) dekódolása.
     *
     * @return array{r?: int, t?: int, e?: string, m?: string, o?: string}
     */
    public function decodeReturn(string $r): array
    {
        $decoded = json_decode((string) base64_decode($r, true), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A rendelés azonosítója az orderRef-ből (`{PREFIX}-{id}-{rand}`), ha a
     * transactionId alapú keresés nem talál (pl. újrapróbált fizetés).
     */
    public function orderIdFromRef(?string $orderRef): ?int
    {
        if (blank($orderRef) || ! preg_match('/^[A-Z0-9]+-(\d+)-/', $orderRef, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Az IPN-válasz teste + aláírás-fejléce: a kapott JSON + `receiveDate`.
     *
     * @param  array<string, mixed>  $ipnBody
     * @return array{0: string, 1: string} [json, signature]
     */
    public function ipnConfirmation(array $ipnBody): array
    {
        $ipnBody['receiveDate'] = now()->toIso8601String();
        $json = json_encode($ipnBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [$json, $this->sign($json)];
    }

    private function orderRef(Order $order): string
    {
        $prefix = Str::of(app(SiteBranding::class)->slug())->upper()->substr(0, 4)->padRight(2, 'X');

        return $prefix.'-'.$order->id.'-'.Str::upper(Str::random(8));
    }

    private function sign(string $data): string
    {
        return base64_encode(hash_hmac('sha384', $data, $this->secretKey(), true));
    }

    private function baseUrl(): string
    {
        return config('services.simplepay.sandbox', true) ? self::SANDBOX_BASE : self::LIVE_BASE;
    }

    private function merchant(): ?string
    {
        return config('services.simplepay.merchant');
    }

    private function secretKey(): string
    {
        return (string) config('services.simplepay.secret_key');
    }
}
