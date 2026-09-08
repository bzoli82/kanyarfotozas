<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Media;
use App\Models\OrganizerPayout;
use App\Services\OrganizerRevenue;
use App\Services\SiteBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Esemény-szervező portál — READ-ONLY. A szervező a saját eseményeihez tartozó
 * statisztikát és a bevétel-részesedését látja, semmit nem szerkeszthet.
 */
class DashboardController extends Controller
{
    public function index(Request $request, OrganizerRevenue $revenue): Response
    {
        $organizer = $request->user();

        return Inertia::render('Organizer/Dashboard', [
            'events' => $revenue->summary($organizer),
            'totals' => $revenue->totals($organizer),
            'payouts' => OrganizerPayout::query()
                ->where('organizer_id', $organizer->id)
                ->with('event:id,name')
                ->orderByDesc('paid_at')
                ->limit(24)
                ->get()
                ->map(fn (OrganizerPayout $p) => [
                    'amount_cents' => $p->amount_cents,
                    'event' => $p->event?->name,
                    'reference' => $p->reference,
                    'note' => $p->note,
                    'paid_at' => $p->paid_at?->toDateString(),
                ]),
        ]);
    }

    public function event(Request $request, Event $event, OrganizerRevenue $revenue): Response
    {
        abort_unless((string) $event->organizer_id === (string) $request->user()->id, 403);

        $event->load('country:id,name_hu,flag_emoji');

        return Inertia::render('Organizer/Event', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'location' => $event->location,
                'country' => $event->country?->name_hu,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status,
            ],
            'revenue' => $revenue->forEvent($event),
            'topMedia' => Media::query()
                ->where('event_id', $event->id)
                ->where('status', Media::STATUS_READY)
                ->withCount(['orders as sales_count' => fn ($q) => $q->where('orders.payment_status', 'paid')])
                ->orderByDesc('sales_count')
                ->limit(12)
                ->get(['id', 'type', 'thumbnail_s3_key'])
                ->map(fn (Media $m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'thumbnail_s3_key' => $m->thumbnail_s3_key,
                    'sales_count' => (int) $m->sales_count,
                ]),
        ]);
    }

    public function export(Request $request, OrganizerRevenue $revenue): StreamedResponse
    {
        $organizer = $request->user();
        $rows = $revenue->summary($organizer);

        $csv = "Esemeny;Datum;Statusz;Brutto_Ft;Rendeles;Vasarlo;Reszesedes_szazalek;Reszesedes_Ft;Kifizetve_Ft;Nyitva_Ft\n";

        foreach ($rows as $r) {
            $csv .= implode(';', [
                str_replace(';', ',', $r['name']),
                $r['event_date'],
                $r['status'],
                $r['gross_cents'],
                $r['orders'],
                $r['buyers'],
                $r['share_percent'],
                $r['share_cents'],
                $r['paid_cents'],
                $r['outstanding_cents'],
            ])."\n";
        }

        $slug = app(SiteBranding::class)->slug();
        $name = Str::slug($organizer->name) ?: 'szervezo';

        return response()->streamDownload(
            fn () => print ($csv),
            "{$slug}-szervezo-{$name}-".now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
