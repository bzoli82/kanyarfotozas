<?php

namespace App\Services;

use App\Models\Event;
use App\Models\OrganizerPayout;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Az esemény-szervezőnek (organizer) járó bevétel-részesedés számítása.
 *
 * A részesedés az esemény médiáiból származó **fizetett** eladások bruttó
 * összegének a `events.organizer_share_percent` százaléka. A már kifizetett
 * részt az `organizer_payouts` tábla rögzíti (kézi könyvelés).
 */
class OrganizerRevenue
{
    /**
     * @return array{
     *   gross_cents: int, orders: int, buyers: int, media_count: int, views: int,
     *   share_percent: int, share_cents: int, paid_cents: int, outstanding_cents: int
     * }
     */
    public function forEvent(Event $event): array
    {
        $base = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', 'paid')
            ->where('media.event_id', $event->id);

        $gross = (int) (clone $base)->sum('order_media.price_cents');
        $orders = (int) (clone $base)->distinct()->count('orders.id');
        $buyers = (int) (clone $base)->distinct()->count('orders.buyer_email');

        $sharePercent = (int) ($event->organizer_share_percent ?? 0);
        $shareCents = (int) round($gross * $sharePercent / 100);

        $paid = $event->organizer_id
            ? (int) OrganizerPayout::query()
                ->where('organizer_id', $event->organizer_id)
                ->where('event_id', $event->id)
                ->sum('amount_cents')
            : 0;

        return [
            'gross_cents' => $gross,
            'orders' => $orders,
            'buyers' => $buyers,
            'media_count' => (int) $event->media()->count(),
            'views' => (int) $event->views()->sum('count'),
            'share_percent' => $sharePercent,
            'share_cents' => $shareCents,
            'paid_cents' => $paid,
            'outstanding_cents' => max(0, $shareCents - $paid),
        ];
    }

    /**
     * Eseményenkénti sorok egy szervezőhöz.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(User $organizer): Collection
    {
        return $organizer->organizedEvents()
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'location' => $event->location,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status,
                ...$this->forEvent($event),
            ])
            ->values();
    }

    /**
     * @return array{gross_cents: int, share_cents: int, paid_cents: int, outstanding_cents: int}
     */
    public function totals(User $organizer): array
    {
        $rows = $this->summary($organizer);

        // A kifizetés lehet esemény-független is (event_id = null), ezért a
        // ténylegesen kifizetett összeget a teljes payout-listából vesszük.
        $paid = (int) OrganizerPayout::query()->where('organizer_id', $organizer->id)->sum('amount_cents');
        $share = (int) $rows->sum('share_cents');

        return [
            'gross_cents' => (int) $rows->sum('gross_cents'),
            'share_cents' => $share,
            'paid_cents' => $paid,
            'outstanding_cents' => max(0, $share - $paid),
        ];
    }
}
