<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Az /admin/stats szuro-kombinacioit (idoszak, fotos, esemeny, media tipus)
 * ket helyen hasznaljuk azonos modon: a lapozhato reszletes tablazatnal es a
 * CSV exportnal — ezert kozos query-epito, hogy a ket hely sose terjen el.
 */
class SalesStatsQuery
{
    /**
     * Egy sor = egy megvasarolt media (order_media pivot), a rendeles/esemeny/
     * fotos/media adataival egyutt betoltve.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, photographer_id?: ?string, event_id?: ?int, type?: ?string}  $filters
     * @return Builder<Pivot>
     */
    public function query(array $filters): Builder
    {
        $query = Order::query()
            ->join('order_media', 'order_media.order_id', '=', 'orders.id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->join('events', 'events.id', '=', 'media.event_id')
            ->leftJoin('users as photographers', 'photographers.id', '=', 'media.photographer_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '<=', $date))
            ->when($filters['photographer_id'] ?? null, fn ($q, $id) => $q->where('media.photographer_id', $id))
            ->when($filters['event_id'] ?? null, fn ($q, $id) => $q->where('media.event_id', $id))
            ->when(
                in_array($filters['type'] ?? null, [Media::TYPE_PHOTO, Media::TYPE_VIDEO], true),
                fn ($q) => $q->where('media.type', $filters['type']),
            );

        return $query->select([
            'orders.id as order_id',
            'orders.created_at as order_date',
            'orders.buyer_email',
            'photographers.name as photographer_name',
            'events.name as event_name',
            'media.id as media_id',
            'media.type as media_type',
            'media.thumbnail_s3_key',
            'order_media.price_cents',
        ]);
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, photographer_id?: ?string, event_id?: ?int, type?: ?string}  $filters
     * @return array{total_revenue_cents: int, media_sold: int, average_order_cents: int, unique_buyers: int, top_event: ?string, top_media_id: ?int}
     */
    public function summary(array $filters): array
    {
        $rows = $this->query($filters)->get();

        $totalRevenue = (int) $rows->sum('price_cents');
        $orderCount = $rows->pluck('order_id')->unique()->count();

        $topEvent = $rows->groupBy('event_name')
            ->map(fn ($group) => $group->sum('price_cents'))
            ->sortDesc()
            ->keys()
            ->first();

        $topMedia = $rows->groupBy('media_id')
            ->map(fn ($group) => $group->sum('price_cents'))
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'total_revenue_cents' => $totalRevenue,
            'media_sold' => $rows->count(),
            'average_order_cents' => $orderCount > 0 ? (int) round($totalRevenue / $orderCount) : 0,
            'unique_buyers' => $rows->pluck('buyer_email')->unique()->count(),
            'top_event' => $topEvent,
            'top_media_id' => $topMedia,
        ];
    }
}
