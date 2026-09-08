<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

/**
 * Kuponkod validalas + kedvezmeny-szamitas — a kosar/checkout oldal es a
 * CheckoutService egyarant ezt hasznalja, hogy a "kedvezmeny elonezet" es a
 * tenyleges rendeles-letrehozas mindig ugyanazt az eredmenyt adja.
 */
class CouponService
{
    /**
     * @return array{coupon: Coupon, discount_cents: int}
     *
     * @throws ValidationException ha a kod nem letezik vagy nem ervenyes
     */
    public function apply(string $code, int $subtotalCents): array
    {
        $coupon = Coupon::query()->where('code', $code)->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw ValidationException::withMessages(['coupon_code' => 'Érvénytelen vagy lejárt kuponkód.']);
        }

        $discount = (int) round($subtotalCents * $coupon->discount_percent / 100);

        return ['coupon' => $coupon, 'discount_cents' => min($discount, $subtotalCents)];
    }
}
