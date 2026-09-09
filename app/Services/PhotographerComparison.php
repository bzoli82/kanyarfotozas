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
    /** Ennyi kész média fölött nézzük a konverziót (kevés médiánál a jel zajos). */
    private const WATCH_MIN_READY = 15;

    /**
     * @return list<array{
     *     id: int, name: string, is_active: bool, revenue_share_percent: int,
     *     media_total: int, media_ready: int, media_sold: int, inquiries: int,
     *     revenue_cents: int, photographer_share_cents: int,
     *     conversion_rate: float, avg_price_cents: int,
     *     flag: string|null, flag_reasons: list<string>
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

        $inquiries = DB::table('contact_messages')
            ->where('contact_type', 'photographer')
            ->whereNotNull('photographer_id')
            ->groupBy('photographer_id')
            ->selectRaw('photographer_id, COUNT(*) as cnt')
            ->pluck('cnt', 'photographer_id');

        $base = $photographers->map(function (User $p) use ($mediaCounts, $sales, $inquiries) {
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
                'inquiries' => (int) ($inquiries[$p->id] ?? 0),
                'revenue_cents' => $revenue,
                'photographer_share_cents' => (int) round($revenue * $share / 100),
                'conversion_rate' => $ready > 0 ? round($sold / $ready * 100, 1) : 0.0,
                'avg_price_cents' => $sold > 0 ? (int) round($revenue / $sold) : 0,
            ];
        });

        // A platform mediánja azoknál, akiknél van értelmes mennyiségű kész média.
        $medianConversion = $this->median(
            $base->where('media_ready', '>=', self::WATCH_MIN_READY)->pluck('conversion_rate')->all()
        );

        return $base->map(function (array $row) use ($medianConversion) {
            $reasons = $this->flagReasons($row, $medianConversion);

            return [...$row, 'flag' => $reasons === [] ? null : 'watch', 'flag_reasons' => $reasons];
        })->all();
    }

    /**
     * Csak a „megnézendő" fotósok — az oldalon kívüli értékesítés lehetséges jelei.
     * SOFT jel: lehet ártalmatlan (új fotós, gyenge esemény), de érdemes ránézni.
     *
     * @return list<array<string, mixed>>
     */
    public function watchlist(): array
    {
        return collect($this->rows())->where('flag', 'watch')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function flagReasons(array $row, ?float $medianConversion): array
    {
        $reasons = [];
        $ready = (int) $row['media_ready'];
        $sold = (int) $row['media_sold'];
        $inq = (int) $row['inquiries'];

        if ($inq >= 3 && $sold === 0) {
            $reasons[] = "{$inq} kérdés a fotóshoz, de egyetlen eladás sincs";
        }

        if ($inq >= 5 && $inq / max(1, $sold) >= 3) {
            $reasons[] = "sok kérdés ({$inq}), kevés eladás ({$sold})";
        }

        if ($ready >= self::WATCH_MIN_READY && $medianConversion !== null && $medianConversion > 0
            && $row['conversion_rate'] < $medianConversion * 0.4) {
            $reasons[] = 'a konverzió jóval a platform-átlag alatt ('.$row['conversion_rate'].'% vs ~'.$medianConversion.'%)';
        }

        return $reasons;
    }

    /**
     * @param  list<float|int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $mid = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 1)
            : (float) $values[$mid];
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
