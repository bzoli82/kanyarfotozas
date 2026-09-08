<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Időszakos (havi vagy heti) statisztika-összesítő a superadmin dashboardhoz
 * (EPIC-18 kiegészítés). Egy sor = egy időszak; a `/admin/dashboard/stats/export`
 * végpont CSV-ként adja vissza. A számítás ugyanazokat a forrásokat használja,
 * mint a dashboard KPI-k (Order paid + order_media + media).
 */
class PeriodicStatsExport
{
    public const GRANULARITY_MONTH = 'month';

    public const GRANULARITY_WEEK = 'week';

    /**
     * @return list<array<string, int|float|string>>
     */
    public function rollup(Carbon $from, Carbon $to, string $granularity = self::GRANULARITY_MONTH): array
    {
        $granularity = $granularity === self::GRANULARITY_WEEK ? self::GRANULARITY_WEEK : self::GRANULARITY_MONTH;

        $cursor = $granularity === self::GRANULARITY_WEEK
            ? $from->copy()->startOfWeek()
            : $from->copy()->startOfMonth();

        $end = $to->copy();
        $rows = [];

        while ($cursor->lte($end)) {
            $periodStart = $cursor->copy();
            $periodEnd = $granularity === self::GRANULARITY_WEEK
                ? $cursor->copy()->endOfWeek()
                : $cursor->copy()->endOfMonth();

            $rows[] = $this->periodRow($periodStart, $periodEnd, $granularity);

            $granularity === self::GRANULARITY_WEEK ? $cursor->addWeek() : $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    /**
     * @return array<string, int|float|string>
     */
    private function periodRow(Carbon $start, Carbon $end, string $granularity): array
    {
        $paidInPeriod = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end]);

        $ordersPaid = (clone $paidInPeriod)->count();
        $revenue = (int) (clone $paidInPeriod)->sum('total_cents');
        $discount = (int) (clone $paidInPeriod)->sum('discount_cents');
        $uniqueBuyers = (clone $paidInPeriod)->distinct()->count('buyer_email');

        $ordersCreated = Order::query()->whereBetween('created_at', [$start, $end])->count();

        $soldRows = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw("
                COUNT(*) as sold,
                COUNT(*) FILTER (WHERE media.type = 'photo') as sold_photo,
                COUNT(*) FILTER (WHERE media.type = 'video') as sold_video,
                COUNT(DISTINCT media.photographer_id) as active_photographers
            ")
            ->first();

        $uploaded = Media::query()->whereBetween('created_at', [$start, $end])->count();

        return [
            'period' => $granularity === self::GRANULARITY_WEEK
                ? $start->format('Y-m-d').' – '.$end->format('Y-m-d')
                : $start->format('Y-m'),
            'revenue_huf' => $revenue,
            'discount_huf' => $discount,
            'orders_paid' => $ordersPaid,
            'orders_created' => $ordersCreated,
            'paid_conversion_pct' => $ordersCreated > 0 ? round($ordersPaid / $ordersCreated * 100, 1) : 0.0,
            'avg_order_huf' => $ordersPaid > 0 ? (int) round($revenue / $ordersPaid) : 0,
            'unique_buyers' => $uniqueBuyers,
            'media_sold' => (int) ($soldRows->sold ?? 0),
            'media_sold_photo' => (int) ($soldRows->sold_photo ?? 0),
            'media_sold_video' => (int) ($soldRows->sold_video ?? 0),
            'media_uploaded' => $uploaded,
            'active_photographers' => (int) ($soldRows->active_photographers ?? 0),
        ];
    }

    /**
     * @param  list<array<string, int|float|string>>  $rows
     */
    public function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Időszak', 'Bevétel (Ft)', 'Kedvezmény (Ft)',
            'Fizetett rendelés', 'Létrehozott rendelés', 'Fizetési konverzió (%)',
            'Átlagos rendelés (Ft)', 'Egyedi vásárló', 'Eladott média',
            'Ebből kép', 'Ebből videó', 'Feltöltött média', 'Aktív fotós',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
