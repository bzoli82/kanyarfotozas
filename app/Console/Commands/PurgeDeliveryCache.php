<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderFulfillment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kanyarfotozas:purge-delivery-cache')]
#[Description('A kézbesítési gyorsítótár takarítása — lejárt/kimerült letöltési token után törli a másolt fájlokat')]
class PurgeDeliveryCache extends Command
{
    public function handle(OrderFulfillment $fulfillment): int
    {
        $maxAgeHours = (int) config('media.delivery_max_age_hours', 24 * 7);

        $orders = Order::query()
            ->whereNot('fulfillment_status', 'pending')
            ->orWhereNotNull('fulfillment_prepared_at')
            ->get();

        $purged = 0;

        foreach ($orders as $order) {
            $tokenDead = ! $order->isTokenValid() || $order->isRefunded();
            $tooOld = $order->fulfillment_prepared_at?->lt(now()->subHours($maxAgeHours)) ?? false;

            if ($tokenDead || $tooOld) {
                $fulfillment->purge($order);
                $purged++;
            }
        }

        $this->info("{$purged} rendelés kézbesítési gyorsítótára törölve.");

        return self::SUCCESS;
    }
}
