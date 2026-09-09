<?php

namespace App\Services;

use App\Models\PhotographerEarning;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Csökkenő platform-díj / növekvő fotós-részesedés a HAVI on-platform volumen
 * alapján (anti-disintermediation: a platformon maradás aktívan jobban éri meg).
 *
 * A superadmin sávokat állít be (`site_settings` `commission_bonus_tiers` JSON):
 *   [{"min_sales": 20, "bonus_percent": 5}, {"min_sales": 50, "bonus_percent": 10}]
 * A fotós az adott naptári hónap n-edik eladásától kapja a bónuszt (n = az adott
 * hónapban eddig rögzített eladások + 1). ELŐRE hat: minden eladás a saját
 * pillanatnyi kulcsát rögzíti a `photographer_earnings.share_percent`-be.
 *
 * Üres sáv-lista = kikapcsolva (a fotós a sima `revenue_share_percent`-jét kapja).
 */
class CommissionBonus
{
    /** A részesedés soha nem lépi túl ezt (platform-fenntartás + tranzakciós díjak). */
    public const SHARE_CAP = 95;

    /**
     * @return list<array{min_sales: int, bonus_percent: int}>
     */
    public function tiers(): array
    {
        $raw = SiteSetting::get('commission_bonus_tiers');
        $decoded = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return [];
        }

        return $this->clean($decoded);
    }

    public function enabled(): bool
    {
        return $this->tiers() !== [];
    }

    /**
     * Az adott naptári hónapban eddig rögzített (paid) eladások száma a fotósnak.
     */
    public function salesThisMonth(string $photographerId, ?Carbon $month = null): int
    {
        $start = ($month ?? now())->copy()->startOfMonth();

        return (int) PhotographerEarning::query()
            ->where('photographer_id', $photographerId)
            ->where('earned_at', '>=', $start)
            ->count();
    }

    /**
     * A tényleges részesedés a KÖVETKEZŐ havi eladásra (a `recordForOrder` hívja).
     */
    public function effectiveShare(string $photographerId, int $baseShare, ?Carbon $month = null): int
    {
        $tiers = $this->tiers();

        if ($tiers === []) {
            return $baseShare;
        }

        $nth = $this->salesThisMonth($photographerId, $month) + 1;
        $bonus = 0;

        foreach ($tiers as $tier) {
            if ($nth >= $tier['min_sales']) {
                $bonus = $tier['bonus_percent'];
            }
        }

        return min(self::SHARE_CAP, $baseShare + $bonus);
    }

    /**
     * A fotós dashboardhoz: hol tart a hónapban + mennyi kell a következő sávig.
     *
     * @return array{
     *   enabled: bool, base_percent: int, sales_this_month: int, current_percent: int,
     *   next: array{min_sales: int, bonus_percent: int, needed: int}|null
     * }
     */
    public function progressFor(User $photographer): array
    {
        $tiers = $this->tiers();
        $base = (int) ($photographer->revenue_share_percent ?? 0);
        $sales = $this->salesThisMonth($photographer->id);

        $currentBonus = 0;
        $next = null;

        foreach ($tiers as $tier) {
            if ($sales >= $tier['min_sales']) {
                $currentBonus = $tier['bonus_percent'];
            } elseif ($next === null) {
                $next = [
                    'min_sales' => $tier['min_sales'],
                    'bonus_percent' => $tier['bonus_percent'],
                    'needed' => $tier['min_sales'] - $sales,
                ];
            }
        }

        return [
            'enabled' => $tiers !== [],
            'base_percent' => $base,
            'sales_this_month' => $sales,
            'current_percent' => min(self::SHARE_CAP, $base + $currentBonus),
            'next' => $next,
        ];
    }

    /**
     * @param  list<array{min_sales: mixed, bonus_percent: mixed}>  $tiers
     */
    public function update(array $tiers): void
    {
        SiteSetting::set('commission_bonus_tiers', json_encode($this->clean($tiers)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return list<array{min_sales: int, bonus_percent: int}>
     */
    private function clean(array $tiers): array
    {
        return collect($tiers)
            ->map(fn ($t): array => [
                'min_sales' => (int) ($t['min_sales'] ?? 0),
                'bonus_percent' => (int) ($t['bonus_percent'] ?? 0),
            ])
            ->filter(fn (array $t): bool => $t['min_sales'] >= 2 && $t['bonus_percent'] >= 1 && $t['bonus_percent'] <= 25)
            ->unique('min_sales')
            ->sortBy('min_sales')
            ->values()
            ->all();
    }
}
