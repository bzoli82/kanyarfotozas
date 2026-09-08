<?php

namespace App\Services\Payments;

/**
 * Egy visszatérítés eredménye — a szolgáltatónál keletkezett hivatkozás
 * (Stripe refund id / SimplePay refund transactionId).
 */
class PaymentRefundResult
{
    public function __construct(public string $reference) {}
}
