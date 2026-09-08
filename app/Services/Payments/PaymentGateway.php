<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Közös felület a fizetési szolgáltatókhoz (Stripe, SimplePay). A CheckoutController
 * csak ezt ismeri; a szolgáltató-specifikus visszatérés/IPN a saját kontrollerében van.
 */
interface PaymentGateway
{
    /** A szolgáltató azonosítója — az `orders.payment_provider` enum egyik értéke. */
    public function provider(): string;

    /** Be van-e állítva (kulcsok) — a kosár csak a konfigurált szolgáltatókat kínálja fel. */
    public function isConfigured(): bool;

    /**
     * Fizetés indítása: létrehozza a tranzakciót a szolgáltatónál, és visszaadja
     * az átirányítási URL-t + a rendeléshez rögzítendő hivatkozást.
     *
     * @throws PaymentException ha a szolgáltató hibát ad / nincs beállítva
     */
    public function startPayment(Order $order): PaymentStartResult;

    /**
     * Visszatérítés a szolgáltatónál (teljes vagy részleges, `$amountCents` = forint).
     *
     * @throws PaymentException ha a szolgáltató hibát ad / nincs beállítva
     */
    public function refund(Order $order, int $amountCents): PaymentRefundResult;
}
