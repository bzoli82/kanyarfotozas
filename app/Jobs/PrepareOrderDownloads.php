<?php

namespace App\Jobs;

use App\Console\Commands\RetryOrderFulfillment;
use App\Mail\ProactiveAlertDigestMail;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderFulfillment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * A sikeres fizetés után a megvásárolt fájlokat átmásolja az archív rétegről
 * (NAS SFTP / R2 privát) a gyors, mindig elérhető `delivery` diskre (kézbesítési
 * gyorsítótár). Így a vásárló-oldali letöltés nem függ az archív réteg
 * elérhetőségétől.
 *
 * Ha az archív épp elakadt, a job egyre ritkábban újrapróbál (perc → óra),
 * ütemezetten is ({@see RetryOrderFulfillment}), végül a
 * `fulfillment_status`-t `failed`-re állítja és a superadmin értesítést kap.
 * A job SOSE dob kivételt — a fizetés lezárását (`markPaid`) nem akaszthatja meg.
 */
class PrepareOrderDownloads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Percben — az n. próba ennyivel később fut (utána: `failed`). */
    private const RETRY_DELAYS = [1, 5, 15, 60, 180, 360, 720];

    private const MAX_ATTEMPTS = 8;

    public function __construct(public int $orderId) {}

    public function handle(OrderFulfillment $fulfillment): void
    {
        $order = Order::with('media')->find($this->orderId);

        if (! $order || ! $order->isPaid() || $order->isRefunded() || $order->deliveryReady()) {
            return;
        }

        if ($fulfillment->prepare($order)) {
            return;
        }

        $order->refresh();

        if ($order->fulfillment_status === 'failed') {
            $this->notifyFailure($order);

            return;
        }

        // Még nem sikerült minden fájl — ütemezünk egy újabb próbát. Sync
        // queue-nál (nincs külön worker) az ütemezett RetryOrderFulfillment
        // parancs próbálkozik újra, nem rekurzálunk.
        if (config('queue.default') === 'sync') {
            return;
        }

        $delay = self::RETRY_DELAYS[min($order->fulfillment_attempts, self::MAX_ATTEMPTS - 1) - 1] ?? end(self::RETRY_DELAYS);

        self::dispatch($this->orderId)->delay(now()->addMinutes(max(1, $delay)));
    }

    private function notifyFailure(Order $order): void
    {
        $alert = [[
            'key' => 'order_fulfillment_failed:'.$order->id,
            'severity' => 'critical',
            'title' => "Nem sikerült előkészíteni a #{$order->id} rendelés letöltését",
            'description' => 'A megvásárolt fájlok nem másolhatók az archív rétegről a kézbesítési gyorsítótárba. Ellenőrizd az archív (NAS / R2) elérhetőségét. '.(string) $order->fulfillment_error,
            'action_url' => rescue(fn () => route('admin.orders.show', $order->id), null, false),
            'action_label' => 'Rendelés megnyitása',
        ]];

        User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email')
            ->each(fn ($email) => rescue(fn () => Mail::to($email)->send(new ProactiveAlertDigestMail($alert)), null, false));
    }
}
