<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\DismissedAlert;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A superadmin dashboard "Proaktiv figyelmeztetesek" panelje (EPIC-18): a
 * rendszer allapotabol kiszamitott, cselekvest igenylo jelzesek. Minden
 * figyelmeztetes stabil `key`-t kap (szabaly + targy), igy elrejtheto
 * (`dismissed_alerts` tabla) egy idoszakra.
 *
 * A 15 percenkent futo `kanyarfotozas:scan-alerts` parancs a kritikus, el nem
 * rejtett figyelmeztetesekrol e-mail-osszefoglalot kuld a superadminoknak.
 */
class ProactiveAlerts
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /** Elrejtes utan ennyi napig marad rejtve egy figyelmeztetes. */
    public const DISMISS_DAYS = 7;

    private const STUCK_PROCESSING_HOURS = 2;

    private const NO_UPLOAD_DAYS = 7;

    /**
     * Minden aktualisan fennallo figyelmeztetes (elrejtesi allapot nelkul).
     *
     * @return list<array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}>
     */
    public function all(): array
    {
        return array_values(array_filter([
            $this->failedMedia(),
            $this->failedFulfillment(),
            $this->stuckProcessing(),
            $this->videosMissingSprite(),
            $this->announcedInPast(),
            $this->noRecentUploads(),
            $this->expiringUnusedTokens(),
            $this->activeCouponsExhausted(),
        ]));
    }

    /**
     * A dashboardon megjelenitendo figyelmeztetesek: az `all()` lista az
     * ervenyben levo elrejtesekkel kiszurve.
     *
     * @return list<array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}>
     */
    public function visible(): array
    {
        $dismissed = $this->dismissedKeys();

        return array_values(array_filter(
            $this->all(),
            fn (array $alert) => ! $dismissed->contains($alert['key']),
        ));
    }

    /**
     * @return list<array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}>
     */
    public function criticalVisible(): array
    {
        return array_values(array_filter(
            $this->visible(),
            fn (array $alert) => $alert['severity'] === self::SEVERITY_CRITICAL,
        ));
    }

    public function dismiss(string $key, ?string $userId): void
    {
        if (! collect($this->all())->contains(fn (array $a) => $a['key'] === $key)) {
            return;
        }

        DismissedAlert::query()->updateOrCreate(
            ['alert_key' => $key],
            ['dismissed_by' => $userId, 'dismissed_until' => now()->addDays(self::DISMISS_DAYS)],
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function dismissedKeys(): Collection
    {
        return DismissedAlert::query()
            ->where('dismissed_until', '>', now())
            ->pluck('alert_key');
    }

    /**
     * @return array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}|null
     */
    private function failedMedia(): ?array
    {
        $count = Media::query()->where('status', Media::STATUS_FAILED)->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'media_failed',
            self::SEVERITY_CRITICAL,
            "{$count} médiafájl feldolgozása meghiúsult",
            'Ezek a képek/videók nem jelennek meg a galériában. Nézd át és indítsd újra a feldolgozásukat.',
            '/admin/dashboard#media-health',
            'Médiaegészség',
        );
    }

    private function failedFulfillment(): ?array
    {
        $count = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->where('fulfillment_status', 'failed')
            ->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'order_fulfillment_failed',
            self::SEVERITY_CRITICAL,
            "{$count} kifizetett rendelés letöltése nem előkészíthető",
            'A megvásárolt fájlok nem másolhatók az archív rétegről (NAS / R2) a kézbesítési gyorsítótárba — ellenőrizd az archív elérhetőségét. A vásárlók addig nem, vagy csak lassan tudnak letölteni.',
            '/admin/orders?status=paid',
            'Rendelések',
        );
    }

    private function stuckProcessing(): ?array
    {
        $threshold = now()->subHours(self::STUCK_PROCESSING_HOURS);
        $count = Media::query()
            ->where('status', Media::STATUS_PROCESSING)
            ->where('created_at', '<', $threshold)
            ->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'media_stuck',
            self::SEVERITY_WARNING,
            "{$count} médiafájl több mint ".self::STUCK_PROCESSING_HOURS.' órája feldolgozás alatt',
            'Lehet, hogy elakadt a queue worker. Ellenőrizd, hogy fut-e a `php artisan queue:work`.',
            '/admin/dashboard#media-health',
            'Médiaegészség',
        );
    }

    private function videosMissingSprite(): ?array
    {
        $count = Media::query()
            ->where('type', Media::TYPE_VIDEO)
            ->where('status', Media::STATUS_READY)
            ->whereNull('preview_sprite_s3_key')
            ->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'videos_no_sprite',
            self::SEVERITY_WARNING,
            "{$count} kész videóhoz hiányzik a scrub-előnézet",
            'Ezeknél a videóknál nem működik a bökdöső előnézet-sáv. Futtasd újra a feldolgozást.',
            '/admin/dashboard#media-health',
            'Médiaegészség',
        );
    }

    private function announcedInPast(): ?array
    {
        $events = Event::query()
            ->where('status', Event::STATUS_ANNOUNCED)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', now())
            ->count();

        if ($events === 0) {
            return null;
        }

        return $this->alert(
            'announced_past',
            self::SEVERITY_WARNING,
            "{$events} „hamarosan” esemény már elmúlt",
            'Ezek az események még „announced” státuszban vannak, pedig a kezdésük már elmúlt. Élesítsd őket, vagy töltsd fel a médiát.',
            '/admin/events',
            'Események',
        );
    }

    private function noRecentUploads(): ?array
    {
        $latest = Media::query()->max('created_at');

        if ($latest !== null && Carbon::parse($latest)->gt(now()->subDays(self::NO_UPLOAD_DAYS))) {
            return null;
        }

        return $this->alert(
            'no_recent_uploads',
            self::SEVERITY_INFO,
            'Rég volt új feltöltés',
            'Az elmúlt '.self::NO_UPLOAD_DAYS.' napban nem került fel új médiafájl. Ha volt esemény, kérd meg a fotósokat a feltöltésre.',
            null,
            null,
        );
    }

    private function expiringUnusedTokens(): ?array
    {
        $count = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereNotNull('download_token')
            ->where('download_token_uses', 0)
            ->whereBetween('token_expires_at', [now(), now()->addDay()])
            ->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'tokens_expiring_unused',
            self::SEVERITY_INFO,
            "{$count} kifizetett rendelés letöltési linkje 24 órán belül lejár letöltés nélkül",
            'A vásárlók automatikus emlékeztetőt kapnak, de érdemes rajta tartani a szemed — lejárat után új linket kell kiadni.',
            null,
            null,
        );
    }

    private function activeCouponsExhausted(): ?array
    {
        $count = Coupon::query()
            ->where('active', true)
            ->whereNotNull('max_uses')
            ->whereColumn('used_count', '>=', 'max_uses')
            ->count();

        if ($count === 0) {
            return null;
        }

        return $this->alert(
            'coupons_exhausted',
            self::SEVERITY_INFO,
            "{$count} aktív kuponkód elérte a felhasználási limitjét",
            'Ezek a kuponok már nem használhatók, de még aktívak. Kapcsold ki vagy emeld a limitet.',
            null,
            null,
        );
    }

    /**
     * @return array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}
     */
    private function alert(
        string $key,
        string $severity,
        string $title,
        string $description,
        ?string $actionUrl,
        ?string $actionLabel,
    ): array {
        return [
            'key' => $key,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
        ];
    }
}
