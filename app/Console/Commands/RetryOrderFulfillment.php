<?php

namespace App\Console\Commands;

use App\Jobs\PrepareOrderDownloads;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kanyarfotozas:retry-order-fulfillment')]
#[Description('A kézbesítési gyorsítótárba még be nem másolt (fizetett) rendelések újrapróbálása')]
class RetryOrderFulfillment extends Command
{
    public function handle(): int
    {
        $orders = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->where('fulfillment_status', 'pending')
            ->whereNotNull('download_token')
            ->where('token_expires_at', '>', now())
            ->get();

        foreach ($orders as $order) {
            PrepareOrderDownloads::dispatch($order->id);
        }

        $this->info("{$orders->count()} rendelés kézbesítése újrapróbálva.");

        return self::SUCCESS;
    }
}
