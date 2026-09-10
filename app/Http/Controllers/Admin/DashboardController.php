<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImageMedia;
use App\Jobs\ProcessVideoMedia;
use App\Mail\OrderConfirmationMail;
use App\Models\Media;
use App\Models\Order;
use App\Models\PhotographerPayout;
use App\Services\CommissionBonus;
use App\Services\DashboardStatsService;
use App\Services\LegalPages;
use App\Services\OnboardingChecklist;
use App\Services\PeriodicStatsExport;
use App\Services\PhotographerComparison;
use App\Services\PhotographerPayoutService;
use App\Services\ProactiveAlerts;
use App\Services\SiteBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Teljes superadmin/admin dashboard: KPI kartyak + diagramok + legfrissebb
     * esemenyek/rendelesek + biztonsagi figyelmeztetes — lasd master.txt 9.1.
     */
    public function admin(Request $request, DashboardStatsService $stats, ProactiveAlerts $alerts, PhotographerComparison $comparison): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'kpis' => $stats->kpis(),
            'charts' => [
                'revenue_trend' => $stats->revenueTrend(),
                'media_type_split' => $stats->mediaTypeSplit(),
                'top_events' => $stats->topEvents(),
                'top_photographers' => $stats->topPhotographers(),
                'processing_status' => $stats->processingStatusBreakdown(),
            ],
            'latestEvents' => $stats->latestEvents(),
            'latestOrders' => $stats->latestOrders(),
            'security' => $stats->securityAlerts(),
            'alerts' => $alerts->visible(),
            'funnel' => $stats->conversionFunnel(),
            'eventPerformance' => $stats->eventPerformance(),
            'mediaHealth' => $stats->mediaHealth(),
            'forecast' => $stats->revenueForecast(),
            'photographerComparison' => $comparison->rows(),
            // Csak superadminnak: fotósok, akiknél az oldalon kívüli értékesítés jelei lehetnek.
            'conversionWatch' => $request->user()?->role === 'superadmin' ? $comparison->watchlist() : [],
            // „Első lépések" — csak superadminnak, csak amíg nincs kész / elrejtve.
            'onboarding' => $request->user()?->role === 'superadmin'
                ? app(OnboardingChecklist::class)->forDashboard($request->user())
                : null,
        ]);
    }

    /**
     * „Első lépések" kártya elrejtése (superadmin). Bármikor visszahozható a
     * beállítás törlésével — de a felület nem ad hozzá gombot (a Kritikus
     * beállítások zöld/sárga/piros úgyis mindig mutatja a hiányokat).
     */
    public function dismissOnboarding(OnboardingChecklist $checklist): RedirectResponse
    {
        $checklist->dismiss();

        return back()->with('success', '„Első lépések" elrejtve.');
    }

    /**
     * "Proaktiv figyelmeztetesek" panel — egy figyelmeztetes elrejtese
     * (App\Services\ProactiveAlerts::DISMISS_DAYS napig).
     */
    public function dismissAlert(Request $request, ProactiveAlerts $alerts): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'string']])['key'];

        $alerts->dismiss($key, $request->user()?->id);

        return back()->with('success', 'Figyelmeztetés elrejtve.');
    }

    /**
     * "Mediaegeszseg" panel — egy elakadt/hibas media ujrafeldolgozasa.
     */
    public function reprocessMedia(Media $media): RedirectResponse
    {
        abort_if($media->status === Media::STATUS_READY, 422, 'Ez a média már kész.');

        $media->update(['status' => Media::STATUS_PROCESSING]);

        $media->isVideo()
            ? ProcessVideoMedia::dispatch($media->id)
            : ProcessImageMedia::dispatch($media->id);

        return back()->with('success', "Média #{$media->id} újrafeldolgozása elindítva.");
    }

    /**
     * Időszakos (havi/heti) statisztika-összesítő CSV — a dashboard "Statisztika
     * export" panelje. `from`/`to` formátuma YYYY-MM (havi) vagy YYYY-MM-DD.
     */
    public function exportPeriodicStats(Request $request, PeriodicStatsExport $export): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'granularity' => ['nullable', 'in:month,week'],
        ]);

        $to = $this->parseDate($data['to'] ?? null) ?? Carbon::now();
        $from = $this->parseDate($data['from'] ?? null) ?? $to->copy()->subMonths(11)->startOfMonth();
        $granularity = $data['granularity'] ?? PeriodicStatsExport::GRANULARITY_MONTH;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $csv = $export->toCsv($export->rollup($from, $to, $granularity));
        $filename = app(SiteBranding::class)->slug()."-statisztika-{$from->format('Y-m')}_{$to->format('Y-m')}.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return preg_match('/^\d{4}-\d{2}$/', $value)
                ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
                : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function exportPhotographerComparison(PhotographerComparison $comparison): StreamedResponse
    {
        $csv = $comparison->toCsv();
        $filename = app(SiteBranding::class)->slug().'-fotos-osszehasonlitas-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * A "legfrissebb rendelesek" panel "Link ujrakuldes" gombja — ujra elkuldi
     * a visszaigazolo e-mailt (letoltesi linkkel) egy mar fizetett rendelesre.
     */
    public function resendOrderEmail(Order $order): RedirectResponse
    {
        abort_unless($order->isPaid(), 422);

        Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order));

        return back()->with('success', 'A visszaigazoló e-mail újraküldve.');
    }

    /**
     * Minimalis fotos dashboard. A teljes verzio (sajat beveteli trend stb.) az EPIC-09-ben keszul.
     */
    public function photographer(Request $request, PhotographerPayoutService $payouts): Response
    {
        $user = $request->user();

        $outstanding = $payouts->outstanding($user);

        return Inertia::render('Photographer/Dashboard', [
            'stats' => [
                'media_total' => Media::query()->where('photographer_id', $user->id)->count(),
                'media_ready' => Media::query()->where('photographer_id', $user->id)->where('status', Media::STATUS_READY)->count(),
                'media_processing' => Media::query()->where('photographer_id', $user->id)->where('status', Media::STATUS_PROCESSING)->count(),
                'media_failed' => Media::query()->where('photographer_id', $user->id)->where('status', Media::STATUS_FAILED)->count(),
            ],
            'reportPrefs' => [
                'report_weekly' => (bool) $user->report_weekly,
                'report_monthly' => (bool) $user->report_monthly,
            ],
            'commissionBonus' => app(CommissionBonus::class)->progressFor($user),
            'agreementPending' => $user->agreed_terms_at === null,
            'agreementHtml' => $user->agreed_terms_at === null ? app(LegalPages::class)->photographerAgreementHtml() : null,
            'earnings' => [
                'revenue_share_percent' => (int) $user->revenue_share_percent,
                'outstanding_cents' => $outstanding['amount_cents'],
                'outstanding_count' => $outstanding['media_count'],
                'total_paid_cents' => (int) PhotographerPayout::query()
                    ->where('photographer_id', $user->id)
                    ->where('status', PhotographerPayout::STATUS_PAID)
                    ->sum('amount_cents'),
                'payouts' => PhotographerPayout::query()
                    ->where('photographer_id', $user->id)
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get()
                    ->map(fn (PhotographerPayout $p) => [
                        'payout_number' => $p->payout_number,
                        'status' => $p->status,
                        'amount_cents' => $p->amount_cents,
                        'media_count' => $p->media_count,
                        'method' => $p->method,
                        'paid_at' => $p->paid_at?->toIso8601String(),
                        'created_at' => $p->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }
}
