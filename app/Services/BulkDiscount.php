<?php

namespace App\Services;

use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Automatikus mennyiségi kedvezmény — kuponkód nélkül. A superadmin
 * sávokat (`tier`) állít be: „N+ tétel EGY eseményből → X% kedvezmény
 * annak az eseménynek a tételeire". A kosárban több esemény is lehet;
 * a kedvezmény eseményenként, külön számolódik (a legmagasabb elért sáv).
 *
 * Sávok: `site_settings` `bulk_discount_tiers` JSON, pl.
 *   [{"min": 5, "percent": 10}, {"min": 10, "percent": 20}]
 * Üres lista = a funkció KI.
 */
class BulkDiscount
{
    /**
     * A rendezett, érvényes sávok (min növekvő).
     *
     * @return list<array{min: int, percent: int}>
     */
    public function tiers(): array
    {
        $raw = SiteSetting::get('bulk_discount_tiers');
        $decoded = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($t): array => [
                'min' => (int) ($t['min'] ?? 0),
                'percent' => (int) ($t['percent'] ?? 0),
            ])
            ->filter(fn (array $t): bool => $t['min'] >= 2 && $t['percent'] >= 1 && $t['percent'] <= 90)
            ->unique('min')
            ->sortBy('min')
            ->values()
            ->all();
    }

    public function enabled(): bool
    {
        return $this->tiers() !== [];
    }

    /**
     * @param  iterable<array{event_id: int|null, price_cents: int}>  $items
     * @return array{
     *   discount_cents: int,
     *   groups: list<array{event_id: int, count: int, percent: int, discount_cents: int}>,
     *   hints: list<array{event_id: int, needed: int, percent: int}>
     * }
     */
    public function forItems(iterable $items): array
    {
        $tiers = $this->tiers();

        if ($tiers === []) {
            return ['discount_cents' => 0, 'groups' => [], 'hints' => []];
        }

        $byEvent = [];
        foreach ($items as $item) {
            $eventId = $item['event_id'] ?? null;
            if ($eventId === null) {
                continue; // esemény nélküli tétel nem kap „egy eseményből" kedvezményt
            }
            $byEvent[$eventId][] = (int) $item['price_cents'];
        }

        $total = 0;
        $groups = [];
        $hints = [];
        $topTier = end($tiers)['percent'];

        foreach ($byEvent as $eventId => $prices) {
            $count = count($prices);
            $percent = 0;
            foreach ($tiers as $tier) {
                if ($count >= $tier['min']) {
                    $percent = $tier['percent'];
                }
            }

            if ($percent > 0) {
                $discount = (int) round(array_sum($prices) * $percent / 100);
                $total += $discount;
                $groups[] = ['event_id' => (int) $eventId, 'count' => $count, 'percent' => $percent, 'discount_cents' => $discount];
            }

            // „Már csak N kép a következő kedvezmény-sávig" — konverzió-ösztönző.
            if ($percent < $topTier) {
                foreach ($tiers as $tier) {
                    if ($tier['percent'] > $percent && $count < $tier['min'] && $tier['min'] - $count <= 3) {
                        $hints[] = ['event_id' => (int) $eventId, 'needed' => $tier['min'] - $count, 'percent' => $tier['percent']];
                        break;
                    }
                }
            }
        }

        return ['discount_cents' => $total, 'groups' => $groups, 'hints' => $hints];
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array{discount_cents: int, groups: list<array{event_id: int, count: int, percent: int, discount_cents: int}>}
     */
    public function forMedia(Collection $media): array
    {
        return $this->forItems(
            $media->map(fn (Media $m): array => ['event_id' => $m->event_id, 'price_cents' => (int) $m->price_cents]),
        );
    }

    /**
     * @param  list<array{min: mixed, percent: mixed}>  $tiers
     */
    public function update(array $tiers): void
    {
        $clean = collect($tiers)
            ->map(fn ($t): array => ['min' => (int) ($t['min'] ?? 0), 'percent' => (int) ($t['percent'] ?? 0)])
            ->filter(fn (array $t): bool => $t['min'] >= 2 && $t['percent'] >= 1 && $t['percent'] <= 90)
            ->unique('min')
            ->sortBy('min')
            ->values()
            ->all();

        SiteSetting::set('bulk_discount_tiers', json_encode($clean));
    }
}
