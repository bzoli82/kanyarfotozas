<?php

namespace App\Services\Payments;

/**
 * Egy fizetés-indítás eredménye: hová irányítjuk a vásárlót, és milyen
 * hivatkozással találjuk meg a rendelést a visszatéréskor / IPN-kor.
 */
class PaymentStartResult
{
    public function __construct(
        public string $redirectUrl,
        public string $reference,
        // A szolgáltató saját rendelés-hivatkozása (SimplePay orderRef) — a visszatérítéshez.
        public ?string $providerOrderRef = null,
    ) {}
}
