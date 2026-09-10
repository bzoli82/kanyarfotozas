<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSubscription;
use App\Models\EventView;
use App\Models\FailedLoginAttempt;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A superadmin/admin fooldali dashboard (/admin/dashboard) osszes KPI- es
 * diagram-adatat szamitja — lasd master.txt 9.1 fejezet. Kulon service-be
 * szervezve, hogy a controller vekony maradjon es a szamitasok onmagukban
 * tesztelhetok legyenek.
 */
class DashboardStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $paidOrders = Order::query()->where('payment_status', Order::STATUS_PAID);

        return [
            'revenue' => [
                'today' => (clone $paidOrders)->where('created_at', '>=', $today)->sum('total_cents'),
                'month' => (clone $paidOrders)->where('created_at', '>=', $monthStart)->sum('total_cents'),
                'all_time' => (clone $paidOrders)->sum('total_cents'),
            ],
            'media_sold' => [
                'today' => $this->soldMediaCount($today),
                'month' => $this->soldMediaCount($monthStart),
                'all_time' => $this->soldMediaCount(null),
            ],
            'active_orders_today' => (clone $paidOrders)->where('created_at', '>=', $today)->count(),
            'active_download_tokens' => Order::query()
                ->whereNotNull('download_token')
                ->where('token_expires_at', '>', now())
                ->count(),
            'uploaded_media' => [
                'today' => Media::query()->where('created_at', '>=', $today)->count(),
                'month' => Media::query()->where('created_at', '>=', $monthStart)->count(),
            ],
            'failed_media_count' => Media::query()->where('status', Media::STATUS_FAILED)->count(),
            'photographers' => [
                'active' => User::query()->where('role', User::ROLE_PHOTOGRAPHER)->where('is_active', true)->count(),
                'inactive' => User::query()->where('role', User::ROLE_PHOTOGRAPHER)->where('is_active', false)->count(),
            ],
            // Helyszin-ertesito feliratkozok: hany kulonbozo e-mail cim var esemeny-ertesitest (EPIC-17).
            'subscribers_count' => EventSubscription::query()->distinct()->count('email'),
        ];
    }

    private function soldMediaCount(?\DateTimeInterface $since): int
    {
        return DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->when($since, fn ($q) => $q->where('orders.created_at', '>=', $since))
            ->count();
    }

    /**
     * @return array{labels: string[], data: int[]}
     */
    public function revenueTrend(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Order::query()
            ->selectRaw('DATE(created_at) as day, SUM(total_cents) as total')
            ->where('payment_status', Order::STATUS_PAID)
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('m.d');
            $data[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Egy adott fotos sajat beveteli trendje (fotos reszletes profil, Attekinto tab).
     *
     * @return array{labels: string[], data: int[]}
     */
    public function revenueTrendForPhotographer(string $photographerId, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->where('media.photographer_id', $photographerId)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('DATE(orders.created_at) as day, SUM(order_media.price_cents) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('m.d');
            $data[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array{photo: int, video: int}
     */
    public function mediaTypeSplit(): array
    {
        $rows = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->selectRaw('media.type, COUNT(*) as total')
            ->groupBy('media.type')
            ->pluck('total', 'type');

        return ['photo' => (int) ($rows['photo'] ?? 0), 'video' => (int) ($rows['video'] ?? 0)];
    }

    /**
     * @return array{labels: string[], data: int[]}
     */
    public function topEvents(int $limit = 5): array
    {
        $rows = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->join('events', 'events.id', '=', 'media.event_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->selectRaw('events.name, SUM(order_media.price_cents) as revenue')
            ->groupBy('events.id', 'events.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return ['labels' => $rows->pluck('name')->all(), 'data' => $rows->pluck('revenue')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * Esemeny-szintu teljesitmeny: megtekintes -> rendeles -> fizetett -> bevetel,
     * eseményenkent (a konverzios tolcser lebontasa). Csak a `live` esemenyek,
     * amelyeknek az idoszakban volt megtekintese VAGY rendelese.
     *
     * @return list<array{id: int, name: string, slug: string, event_date: string|null, views: int, orders: int, paid_orders: int, revenue_cents: int, conversion: float|null}>
     */
    public function eventPerformance(int $days = 30, int $limit = 15): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $viewsSub = DB::table('event_views')
            ->select('event_id', DB::raw('SUM(count) as views'))
            ->where('viewed_on', '>=', $from->toDateString())
            ->groupBy('event_id');

        $rows = DB::table('events')
            ->leftJoinSub($viewsSub, 'v', 'v.event_id', '=', 'events.id')
            ->leftJoin('media', 'media.event_id', '=', 'events.id')
            ->leftJoin('order_media', 'order_media.media_id', '=', 'media.id')
            ->leftJoin('orders', function ($join) use ($from) {
                $join->on('orders.id', '=', 'order_media.order_id')
                    ->where('orders.created_at', '>=', $from);
            })
            ->where('events.status', Event::STATUS_LIVE)
            ->groupBy('events.id', 'events.name', 'events.slug', 'events.event_date', 'v.views')
            ->havingRaw('COALESCE(v.views, 0) > 0 OR COUNT(DISTINCT orders.id) > 0')
            ->selectRaw(
                'events.id, events.name, events.slug, events.event_date,
                 COALESCE(v.views, 0)::int as views,
                 COUNT(DISTINCT orders.id)::int as orders,
                 COUNT(DISTINCT orders.id) FILTER (WHERE orders.payment_status = ?)::int as paid_orders,
                 COALESCE(SUM(order_media.price_cents) FILTER (WHERE orders.payment_status = ?), 0)::int as revenue_cents',
                [Order::STATUS_PAID, Order::STATUS_PAID]
            )
            ->orderByRaw('revenue_cents DESC, views DESC')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
            'event_date' => $r->event_date,
            'views' => (int) $r->views,
            'orders' => (int) $r->orders,
            'paid_orders' => (int) $r->paid_orders,
            'revenue_cents' => (int) $r->revenue_cents,
            'conversion' => $r->views > 0 ? round($r->paid_orders / $r->views * 100, 1) : null,
        ])->all();
    }

    /**
     * @return array{labels: string[], data: int[]}
     */
    public function topPhotographers(int $limit = 5): array
    {
        $rows = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->join('users', 'users.id', '=', 'media.photographer_id')
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->selectRaw('users.name, SUM(order_media.price_cents) as revenue')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return ['labels' => $rows->pluck('name')->all(), 'data' => $rows->pluck('revenue')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * @return array<string, int>
     */
    public function processingStatusBreakdown(): array
    {
        return Media::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, starts_at: ?string, photographer: ?string, media_count: int, status: string}>
     */
    public function latestEvents(int $limit = 5): array
    {
        return Event::query()
            ->withCount('media')
            ->with('createdBy:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $event->starts_at?->toDateString(),
                'photographer' => $event->createdBy?->name,
                'media_count' => $event->media_count,
                'status' => $event->status,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, created_at: string, email_masked: string, total_cents: int, media_count: int, status: string}>
     */
    public function latestOrders(int $limit = 10): array
    {
        return Order::query()
            ->withCount('media')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->toIso8601String(),
                'email_masked' => $this->maskEmail($order->buyer_email),
                'total_cents' => $order->total_cents,
                'media_count' => $order->media_count,
                'status' => $order->payment_status,
            ])->all();
    }

    /**
     * @return array{active: bool, failed_logins_24h: int, distinct_ips_24h: int}
     */
    public function securityAlerts(): array
    {
        $since = now()->subDay();

        $recent = FailedLoginAttempt::query()->where('created_at', '>=', $since);
        $failedLogins = (clone $recent)->count();
        $distinctIps = (clone $recent)->distinct('ip_hash')->count('ip_hash');

        return [
            // Riasztas, ha 24 oran belul tobb mint 5 sikertelen probalkozas volt (barmilyen fiokra).
            'active' => $failedLogins > 5,
            'failed_logins_24h' => $failedLogins,
            'distinct_ips_24h' => $distinctIps,
        ];
    }

    /**
     * Konverzios tolcser a rendelesek eletciklusabol (EPIC-18). A top-of-funnel
     * (galeria-megtekintesek) az `event_views` napi rollupbol jon — kulso
     * analitika (Plausible) nelkul; ez a lepcso csak akkor jelenik meg, ha mar
     * gyult megtekintes-adat az idoszakban.
     *
     * @return array{days: int, stages: list<array{key: string, label: string, count: int, pct_of_start: float, pct_of_prev: float}>}
     */
    public function conversionFunnel(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $inWindow = Order::query()->where('created_at', '>=', $from);

        $galleryViews = (int) EventView::query()
            ->where('viewed_on', '>=', $from->toDateString())
            ->sum('count');
        $cartsCreated = (clone $inWindow)->count();
        $checkoutStarted = (clone $inWindow)->whereNotNull('payment_provider_reference')->count();
        $paid = (clone $inWindow)->where('payment_status', Order::STATUS_PAID)->count();
        $downloaded = (clone $inWindow)
            ->where('payment_status', Order::STATUS_PAID)
            ->where('download_token_uses', '>', 0)
            ->count();

        $raw = [
            ['key' => 'cart', 'label' => 'Rendelés elindítva', 'count' => $cartsCreated],
            ['key' => 'checkout', 'label' => 'Fizetés elindítva', 'count' => $checkoutStarted],
            ['key' => 'paid', 'label' => 'Kifizetve', 'count' => $paid],
            ['key' => 'downloaded', 'label' => 'Letöltve', 'count' => $downloaded],
        ];

        if ($galleryViews > 0) {
            array_unshift($raw, ['key' => 'gallery', 'label' => 'Galéria megtekintés', 'count' => $galleryViews]);
        }

        $start = max(1, $raw[0]['count']);
        $stages = [];
        $prev = null;
        foreach ($raw as $stage) {
            $stages[] = [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'count' => $stage['count'],
                'pct_of_start' => round($stage['count'] / $start * 100, 1),
                'pct_of_prev' => $prev === null || $prev === 0
                    ? 100.0
                    : round($stage['count'] / $prev * 100, 1),
            ];
            $prev = $stage['count'];
        }

        return ['days' => $days, 'stages' => $stages];
    }

    /**
     * Mediaegeszseg panel (EPIC-18): a feldolgozasi problemak osszefoglaloja.
     *
     * @return array{failed: int, stuck_processing: int, videos_missing_sprite: int, ready_missing_variants: int, plate_recognition: array{enabled: bool, pending: int, unidentifiable: int, video_review: int}, samples: list<array{id: int, event: ?string, type: string, issue: string}>}
     */
    public function mediaHealth(): array
    {
        $stuckThreshold = now()->subHours(2);

        $failed = Media::query()->where('status', Media::STATUS_FAILED)->count();
        $stuck = Media::query()
            ->where('status', Media::STATUS_PROCESSING)
            ->where('created_at', '<', $stuckThreshold)
            ->count();
        $videosNoSprite = Media::query()
            ->where('type', Media::TYPE_VIDEO)
            ->where('status', Media::STATUS_READY)
            ->whereNull('preview_sprite_s3_key')
            ->count();
        $readyMissingVariants = Media::query()
            ->where('status', Media::STATUS_READY)
            ->where(fn ($q) => $q->whereNull('thumbnail_s3_key')->orWhereNull('watermarked_s3_key'))
            ->count();

        $plateEnabled = app(PlateRecognitionSettings::class)->enabled();
        $platePending = $plateEnabled
            ? Media::query()->where('status', Media::STATUS_READY)->where('license_plate_status', Media::PLATE_PENDING)->count()
            : 0;
        $plateUnidentifiable = $plateEnabled
            ? Media::query()->where('status', Media::STATUS_READY)->where('license_plate_status', Media::PLATE_UNIDENTIFIABLE)->count()
            : 0;
        $plateVideoReview = $plateEnabled
            ? Media::query()->where('status', Media::STATUS_READY)->where('type', Media::TYPE_VIDEO)
                ->where('license_plate_status', Media::PLATE_DETECTED)->count()
            : 0;

        $samples = Media::query()
            ->with('event:id,name')
            ->where(function ($q) use ($stuckThreshold) {
                $q->where('status', Media::STATUS_FAILED)
                    ->orWhere(fn ($sub) => $sub->where('status', Media::STATUS_PROCESSING)->where('created_at', '<', $stuckThreshold))
                    ->orWhere(fn ($sub) => $sub->where('type', Media::TYPE_VIDEO)->where('status', Media::STATUS_READY)->whereNull('preview_sprite_s3_key'));
            })
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'event' => $m->event?->name,
                'type' => $m->type,
                'issue' => match (true) {
                    $m->status === Media::STATUS_FAILED => 'Feldolgozás meghiúsult',
                    $m->status === Media::STATUS_PROCESSING => 'Elakadt feldolgozás',
                    default => 'Hiányzó scrub-sprite',
                },
            ])->all();

        return [
            'failed' => $failed,
            'stuck_processing' => $stuck,
            'videos_missing_sprite' => $videosNoSprite,
            'ready_missing_variants' => $readyMissingVariants,
            'plate_recognition' => [
                'enabled' => $plateEnabled,
                'pending' => $platePending,
                'unidentifiable' => $plateUnidentifiable,
                'video_review' => $plateVideoReview,
            ],
            'samples' => $samples,
        ];
    }

    /**
     * Bevetel-elorejelzes (EPIC-18): a legutobbi napok napi bevetelere illesztett
     * linearis trend alapjan a het ill. a honap vegere extrapolalt bevetel.
     *
     * @return array{week: array{actual_cents: int, forecast_cents: int}, month: array{actual_cents: int, forecast_cents: int}, daily_slope_cents: int}
     */
    public function revenueForecast(int $lookbackDays = 30): array
    {
        $from = now()->subDays($lookbackDays - 1)->startOfDay();

        $byDay = Order::query()
            ->selectRaw('DATE(created_at) as day, SUM(total_cents) as total')
            ->where('payment_status', Order::STATUS_PAID)
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        // Linearis regresszio (least squares) a napi bevetelre: x = nap-index, y = bevetel.
        $n = $lookbackDays;
        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $y = (float) ($byDay[$from->copy()->addDays($i)->toDateString()] ?? 0);
            $sumX += $i;
            $sumY += $y;
            $sumXY += $i * $y;
            $sumXX += $i * $i;
        }
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator != 0.0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0.0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $predict = fn (int $dayIndex) => max(0.0, $intercept + ($slope * $dayIndex));

        $todayIndex = $n - 1;

        $weekActual = (int) Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('total_cents');
        $daysLeftThisWeek = (int) floor(now()->diffInDays(now()->endOfWeek(), false));
        $weekForecast = $weekActual;
        for ($d = 1; $d <= $daysLeftThisWeek; $d++) {
            $weekForecast += (int) round($predict($todayIndex + $d));
        }

        $monthActual = (int) Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_cents');
        $daysLeftThisMonth = (int) floor(now()->diffInDays(now()->endOfMonth(), false));
        $monthForecast = $monthActual;
        for ($d = 1; $d <= $daysLeftThisMonth; $d++) {
            $monthForecast += (int) round($predict($todayIndex + $d));
        }

        return [
            'week' => ['actual_cents' => $weekActual, 'forecast_cents' => max($weekActual, $weekForecast)],
            'month' => ['actual_cents' => $monthActual, 'forecast_cents' => max($monthActual, $monthForecast)],
            'daily_slope_cents' => (int) round($slope),
        ];
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, 2);
        $masked = $visible.str_repeat('*', max(1, mb_strlen($local) - 2));

        return "{$masked}@{$domain}";
    }
}
