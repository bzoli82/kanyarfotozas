<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\Payments\SimplePayGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SimplePayIpnController extends Controller
{
    /**
     * SimplePay IPN (Instant Payment Notification) — szerver-szerver, ez a
     * MEGBÍZHATÓ forrás a fizetés lezárásához. A `Signature` fejlécet ellenőrizzük,
     * a választ ugyanazzal az aláírással + `receiveDate` mezővel kell visszaküldeni,
     * különben a SimplePay újrapróbálja a kézbesítést.
     */
    public function handle(Request $request, SimplePayGateway $simplePay, CheckoutService $checkout): Response
    {
        $raw = $request->getContent();

        if (! $simplePay->verifySignature($raw, $request->header('Signature'))) {
            return response('Invalid signature', 400);
        }

        $body = json_decode($raw, true);

        if (! is_array($body)) {
            return response('Invalid body', 400);
        }

        if (($body['status'] ?? null) === 'FINISHED') {
            $order = $this->resolveOrder($simplePay, $body);

            if ($order) {
                $checkout->markPaid($order);
            }
        }

        [$json, $signature] = $simplePay->ipnConfirmation($body);

        return response($json, 200)
            ->header('Content-Type', 'application/json')
            ->header('Signature', $signature);
    }

    /**
     * @param  array{transactionId?: int|string, orderRef?: string}  $body
     */
    private function resolveOrder(SimplePayGateway $simplePay, array $body): ?Order
    {
        $transactionId = (string) ($body['transactionId'] ?? '');

        if (filled($transactionId)) {
            $order = Order::query()->where('payment_provider_reference', $transactionId)->first();
            if ($order) {
                return $order;
            }
        }

        $orderId = $simplePay->orderIdFromRef($body['orderRef'] ?? null);

        return $orderId ? Order::query()->where('id', $orderId)->where('payment_provider', 'simplepay')->first() : null;
    }
}
