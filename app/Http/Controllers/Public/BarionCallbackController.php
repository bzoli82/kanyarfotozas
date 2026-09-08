<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\Payments\BarionGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BarionCallbackController extends Controller
{
    /**
     * Barion IPN/callback — szerver-szerver POST a `CallbackUrl`-re, `paymentId`
     * mezővel. Ez a MEGBÍZHATÓ forrás: a fizetés állapotát a Barion szerveréről
     * kérdezzük vissza (a POSKey a mi titkunk), és `Succeeded` esetén zárjuk le a
     * rendelést. A választ 200-zal kell nyugtázni, különben a Barion újrapróbál.
     */
    public function handle(Request $request, BarionGateway $barion, CheckoutService $checkout): Response
    {
        $paymentId = (string) ($request->input('paymentId') ?? $request->query('paymentId'));

        if (blank($paymentId)) {
            return response('Missing paymentId', 400);
        }

        $state = $barion->paymentState($paymentId);
        $status = $state['Status'] ?? null;

        if ($status === 'Succeeded') {
            $order = $this->resolveOrder($barion, $paymentId, $state);

            if ($order) {
                $checkout->markPaid($order);
            }
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveOrder(BarionGateway $barion, string $paymentId, array $state): ?Order
    {
        $order = Order::query()
            ->where('payment_provider_reference', $paymentId)
            ->where('payment_provider', 'barion')
            ->first();

        if ($order) {
            return $order;
        }

        $orderId = $barion->orderIdFromRequestId($state['PaymentRequestId'] ?? null);

        return $orderId
            ? Order::query()->where('id', $orderId)->where('payment_provider', 'barion')->first()
            : null;
    }
}
