<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Event;
use App\Models\Media;
use App\Services\EventSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * A talalati galeria valaszthato oldalmeretei.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100, 200];

    /**
     * Esemeny lista (/events) — a fooldali keresopanel ide navigal a szurokkel.
     */
    public function index(Request $request, EventSearch $search): Response
    {
        $filters = $this->filtersFromRequest($request);
        $hasGpsFilter = filled($filters['lat'] ?? null) && filled($filters['lon'] ?? null);

        $query = $search->defaultOrder($search->query($filters), $hasGpsFilter);

        $perPage = (int) $request->integer('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;

        $events = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Events/Index', [
            'events' => $events,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Esemeny galeria (/events/{slug}) — vegyes kep+video tartalom, tipus/idopont szures.
     */
    public function show(Request $request, Event $event): Response
    {
        abort_unless(in_array($event->status, [Event::STATUS_LIVE, Event::STATUS_ANNOUNCED], true), 404);

        // Galeria-megtekintes szamlalo (dashboard konverzios tolcser). Munkamenetenkent
        // egyszer szamit, hogy a frissitesek/lapozas ne pumpaljak fel.
        $seenKey = 'viewed_event_'.$event->id;
        if (! $request->session()->has($seenKey)) {
            $event->recordGalleryView();
            $request->session()->put($seenKey, true);
        }

        $type = $request->string('type')->value();
        $shotFrom = $request->string('shot_from')->value();
        $shotTo = $request->string('shot_to')->value();

        $mediaQuery = $event->media()
            ->with('photographer:id,name')
            ->where('status', Media::STATUS_READY)
            ->when(in_array($type, ['photo', 'video'], true), fn ($q) => $q->where('type', $type))
            ->when(filled($shotFrom), fn ($q) => $q->whereTime('shot_at', '>=', $shotFrom))
            ->when(filled($shotTo), fn ($q) => $q->whereTime('shot_at', '<=', $shotTo))
            ->orderBy('shot_at');

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'location' => $event->location,
                'slug' => $event->slug,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status,
                'country' => $event->country ? [
                    'code' => $event->country->code,
                    'name' => $event->country->name,
                    'flag_emoji' => $event->country->flag_emoji,
                ] : null,
            ],
            'media' => $mediaQuery->paginate(24)->withQueryString()->through(fn (Media $m) => (new MediaResource($m))->resolve()),
            'filters' => [
                'type' => $type ?: 'all',
                'shot_from' => $shotFrom,
                'shot_to' => $shotTo,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'location' => $request->string('location')->value() ?: null,
            'countries' => array_filter((array) $request->query('countries', [])),
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'type' => $request->string('type')->value() ?: 'all',
            'photographer_id' => $request->string('photographer_id')->value() ?: null,
            'lat' => $request->query('lat'),
            'lon' => $request->query('lon'),
            'radius' => $request->query('radius', 15),
        ];
    }
}
