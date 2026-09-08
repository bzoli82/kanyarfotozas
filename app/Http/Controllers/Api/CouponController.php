<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Kedvezmeny elonezet a kosar oldalon — meg fizetes/rendeles-letrehozas
     * nelkul megmutatja, hogy egy kod ervenyes-e es mennyi kedvezmenyt ad.
     */
    public function validateCode(Request $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'subtotal_cents' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $result = $coupons->apply($data['code'], $data['subtotal_cents']);
        } catch (ValidationException $e) {
            return response()->json(['valid' => false, 'message' => $e->errors()['coupon_code'][0]], 422);
        }

        return response()->json([
            'valid' => true,
            'discount_cents' => $result['discount_cents'],
            'discount_percent' => $result['coupon']->discount_percent,
        ]);
    }
}
