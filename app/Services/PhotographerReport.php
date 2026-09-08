<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Egy fotos ertekesitesi osszesitoje egy adott idoszakra — a heti/havi
 * e-mail riportokhoz (EPIC-12). A `SalesStatsQuery`-t hasznalja, hogy a
 * szamitas ne terjen el az admin statisztika-oldaltol.
 */
class PhotographerReport
{
    public function __construct(private SalesStatsQuery $stats) {}

    /**
     * @return array{
     *   from: Carbon, to: Carbon, media_sold: int, revenue_cents: int,
     *   photographer_share_cents: int, rows: Collection<int, object>, top_events: Collection<int, array{name: string, revenue_cents: int}>
     * }
     */
    public function forPeriod(User $photographer, Carbon $from, Carbon $to): array
    {
        $rows = $this->stats->query([
            'photographer_id' => $photographer->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ])->orderByDesc('order_date')->get();

        $revenue = (int) $rows->sum('price_cents');

        return [
            'from' => $from,
            'to' => $to,
            'media_sold' => $rows->count(),
            'revenue_cents' => $revenue,
            'photographer_share_cents' => (int) round($revenue * $photographer->revenue_share_percent / 100),
            'rows' => $rows,
            'top_events' => $rows->groupBy('event_name')
                ->map(fn ($group, $name) => ['name' => $name, 'revenue_cents' => (int) $group->sum('price_cents')])
                ->sortByDesc('revenue_cents')
                ->take(5)
                ->values(),
        ];
    }

    /**
     * A reszletes sorok CSV-tartalma (havi riport melleklete).
     */
    public function toCsv(Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Rendelés dátuma', 'Esemény', 'Média ID', 'Típus', 'Ár (Ft)']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                substr((string) $row->order_date, 0, 19),
                $row->event_name,
                $row->media_id,
                $row->media_type === 'video' ? 'Videó' : 'Kép',
                $row->price_cents,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
