<?php

namespace App\Console\Commands;

use App\Mail\DownloadReminderMail;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('roadsidephoto:send-download-reminders')]
#[Description('Emlékeztető e-mail a 48 órán belül lejáró letöltési linkekről (EPIC-12)')]
class SendDownloadReminders extends Command
{
    public function handle(): int
    {
        $orders = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereNotNull('download_token')
            ->whereNull('reminder_sent_at')
            ->where('download_token_uses', '<', Order::TOKEN_MAX_USES)
            ->whereBetween('token_expires_at', [now(), now()->addHours(48)])
            ->get();

        foreach ($orders as $order) {
            Mail::to($order->buyer_email)->send(new DownloadReminderMail($order));
            $order->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info("Elküldött emlékeztetők: {$orders->count()}");

        return self::SUCCESS;
    }
}
