<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Egy e-mail címhez tartozó összes tárolt személyes adat összegyűjtése
 * (GDPR 15. cikk — hozzáférési jog). A hashelt / anonim rekordok nem tartoznak ide.
 */
class PersonalDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function forEmail(string $email): array
    {
        $email = mb_strtolower(trim($email));

        $orders = Order::query()
            ->with(['media:id,type', 'media.event:id,name,location'])
            ->whereRaw('LOWER(buyer_email) = ?', [$email])
            ->orderBy('id')
            ->get()
            ->map(fn (Order $o) => [
                'order_id' => $o->id,
                'created_at' => $o->created_at?->toIso8601String(),
                'total_huf' => $o->total_cents,
                'refunded_huf' => $o->refunded_cents,
                'payment_status' => $o->payment_status,
                'payment_provider' => $o->payment_provider,
                'items' => $o->media->map(fn ($m) => [
                    'type' => $m->type,
                    'event' => $m->event?->name,
                    'location' => $m->event?->location,
                ])->all(),
            ])->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'email' => $email,
            'orders' => $orders,
            'contact_messages' => DB::table('contact_messages')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get(['name', 'email', 'message', 'created_at'])
                ->map(fn ($r) => (array) $r)->all(),
            'event_subscriptions' => DB::table('event_subscriptions')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get(['location', 'created_at', 'last_notified_at'])
                ->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
