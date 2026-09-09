<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Egyszeri emlékeztető azoknak, akik elindítottak egy vásárlást (pending rendelés),
 * de 24 órán belül nem fizettek. Csak egyszer megy ki rendelésenként.
 */
#[Signature('roadsidephoto:send-abandoned-cart-reminders')]
#[Description('Egyszeri emlékeztető a 24 órán belül félbehagyott (ki nem fizetett) vásárlásokról')]
class SendAbandonedCartReminders extends Command
{
    /** Ennyi óra után megy ki az emlékeztető. */
    private const AFTER_HOURS = 24;

    /** Ennél régebbi félbehagyott rendelésre már nem küldünk. */
    private const BEFORE_HOURS = 72;

    public function handle(): int
    {
        $orders = Order::query()
            ->where('payment_status', Order::STATUS_PENDING)
            ->whereNull('abandoned_reminder_sent_at')
            ->whereNotNull('buyer_email')
            ->whereBetween('created_at', [now()->subHours(self::BEFORE_HOURS), now()->subHours(self::AFTER_HOURS)])
            ->whereHas('media', fn ($q) => $q->where('status', Media::STATUS_READY))
            ->get();

        $sent = 0;

        foreach ($orders as $order) {
            // Ha ugyanaz a vevő azóta már fizetett egy rendelést, nem zaklatjuk.
            $paidSince = Order::query()
                ->where('buyer_email', $order->buyer_email)
                ->where('payment_status', Order::STATUS_PAID)
                ->where('created_at', '>=', $order->created_at)
                ->exists();

            if ($paidSince) {
                $order->forceFill(['abandoned_reminder_sent_at' => now()])->save();

                continue;
            }

            Mail::to($order->buyer_email)->send(new AbandonedCartMail($order));
            $order->forceFill(['abandoned_reminder_sent_at' => now()])->save();
            $sent++;
        }

        $this->info("Elküldött elhagyott-kosár emlékeztetők: {$sent}");

        return self::SUCCESS;
    }
}
