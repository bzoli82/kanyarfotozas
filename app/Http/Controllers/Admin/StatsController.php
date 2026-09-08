<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\SalesStatsQuery;
use App\Services\SiteBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatsController extends Controller
{
    private function filters(Request $request): array
    {
        return [
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'photographer_id' => $request->string('photographer_id')->value() ?: null,
            'event_id' => $request->integer('event_id') ?: null,
            'type' => $request->string('type')->value() ?: null,
        ];
    }

    public function index(Request $request, SalesStatsQuery $salesStats, DashboardStatsService $dashboard): InertiaResponse
    {
        $filters = $this->filters($request);

        $sort = $request->string('sort')->value() ?: 'order_date';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $sortable = ['order_date', 'price_cents', 'photographer_name', 'event_name'];
        $sort = in_array($sort, $sortable, true) ? $sort : 'order_date';

        $rows = $salesStats->query($filters)
            ->orderBy($sort, $direction)
            ->paginate(50)
            ->withQueryString();

        $topMediaId = $salesStats->summary($filters)['top_media_id'];

        return Inertia::render('Admin/Stats', [
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => $salesStats->summary($filters),
            'rows' => $rows->through(fn ($row) => [
                'order_id' => $row->order_id,
                'order_date' => $row->order_date,
                'buyer_email_masked' => $dashboard->maskEmail($row->buyer_email),
                'photographer_name' => $row->photographer_name,
                'event_name' => $row->event_name,
                'media_id' => $row->media_id,
                'media_type' => $row->media_type,
                'thumbnail_s3_key' => $row->thumbnail_s3_key,
                'price_cents' => $row->price_cents,
                'is_top_media' => $row->media_id === $topMediaId,
            ]),
            'photographers' => User::query()->where('role', User::ROLE_PHOTOGRAPHER)->orderBy('name')->get(['id', 'name']),
            'events' => Event::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, SalesStatsQuery $salesStats, DashboardStatsService $dashboard): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $salesStats->query($filters)->orderBy('order_date')->get();

        $filename = app(SiteBranding::class)->slug().'-ertekesitesek-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $dashboard) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Rendelés dátuma', 'Fotós', 'Esemény', 'Média ID', 'Típus', 'Ár (Ft)', 'Vásárló e-mail']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    Str::of($row->order_date)->substr(0, 19),
                    $row->photographer_name,
                    $row->event_name,
                    $row->media_id,
                    $row->media_type === 'video' ? 'Videó' : 'Kép',
                    $row->price_cents,
                    $dashboard->maskEmail($row->buyer_email),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
