<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fotos osszehasonlito tablazat a superadmin dashboardon (EPIC-18): fotosonkent
 * a feltoltott / kesz / eladott media, bevetel, a fotos reszesedese, es a
 * konverzios arany (eladott / kesz media). Rendezhetoen a frontenden, CSV-kent
 * a `/admin/photographers/comparison/export` vegponton.
 */
class PhotographerComparison
{
    /**
     * @return list<array{
     *     id: int, name: string, is_active: bool, revenue_share_percent: int,
     *     media_total: int, media_ready: int, media_sold: int,
     *     revenue_cents: int, photographer_share_cents: int,
     *     conversion_rate: float, avg_price_cents: int
     * }>
     */
    public function rows(): array
    {
        $photographers = User::query()
            ->where('role', User::ROLE_PHOTOGRAPHER)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'revenue_share_percent']);

        $mediaCounts = DB::table('media')
            ->selectRaw("photographer_id, COUNT(*) as total, COUNT(*) FILTER (WHERE status = 'ready') as ready")
            ->whereNotNull('photographer_id')
            ->groupBy('photographer_id')
            ->get()
            ->keyBy('photographer_id');

        $sales = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->whereNotNull('media.photographer_id')
            ->groupBy('media.photographer_id')
            ->selectRaw('media.photographer_id, COUNT(*) as sold, SUM(order_media.price_cents) as revenue')
            ->get()
            ->keyBy('photographer_id');

        return $photographers->map(function (User $p) use ($mediaCounts, $sales) {
            $mc = $mediaCounts->get($p->id);
            $sale = $sales->get($p->id);

            $ready = (int) ($mc->ready ?? 0);
            $sold = (int) ($sale->sold ?? 0);
            $revenue = (int) ($sale->revenue ?? 0);
            $share = (int) ($p->revenue_share_percent ?? 0);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'is_active' => (bool) $p->is_active,
                'revenue_share_percent' => $share,
                'media_total' => (int) ($mc->total ?? 0),
                'media_ready' => $ready,
                'media_sold' => $sold,
                'revenue_cents' => $revenue,
                'photographer_share_cents' => (int) round($revenue * $share / 100),
                'conversion_rate' => $ready > 0 ? round($sold / $ready * 100, 1) : 0.0,
                'avg_price_cents' => $sold > 0 ? (int) round($revenue / $sold) : 0,
            ];
        })->all();
    }

    public function toCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Fotós', 'Aktív', 'Jutalék %', 'Feltöltött média', 'Kész média',
            'Eladott média', 'Konverzió %', 'Bevétel (Ft)', 'Fotós részesedése (Ft)', 'Átlagár (Ft)',
        ]);

        foreach ($this->rows() as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['is_active'] ? 'igen' : 'nem',
                $row['revenue_share_percent'],
                $row['media_total'],
                $row['media_ready'],
                $row['media_sold'],
                $row['conversion_rate'],
                $row['revenue_cents'],
                $row['photographer_share_cents'],
                $row['avg_price_cents'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        return collect($this->rows());
    }
}
