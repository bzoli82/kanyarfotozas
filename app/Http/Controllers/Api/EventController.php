<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Event;
use App\Models\Media;
use App\Services\PhotographerVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private PhotographerVisibility $visibility) {}

    /**
     * Nyilvanos, szurheto esemeny lista.
     *
     * Egyelore a terkep-modalhoz (EPIC-10 elozetes) es a fooldali keresopanelhez
     * szukseges minimalis mezokkel. A teljes szures (orszag, datum, tavolsag, tipus)
     * az EPIC-06/EPIC-10-ben egeszul ki.
     */
    public function index(Request $request): JsonResponse
    {
        $countryCodes = array_filter((array) $request->query('countries', []));

        $photographerId = $this->visibility->attributionPublic() ? ($request->string('photographer_id')->value() ?: null) : null;
        $type = in_array($request->query('type'), [Media::TYPE_PHOTO, Media::TYPE_VIDEO], true) ? $request->query('type') : null;

        // A markereken a szűrésnek megfelelő médiaszám látszik.
        $mediaCountFilter = function ($q) use ($photographerId, $type) {
            $q->when($photographerId, fn ($m) => $m->where('photographer_id', $photographerId))
                ->when($type, fn ($m) => $m->where('type', $type));
        };

        $events = Event::query()
            ->with('country:id,code,name_hu,name_en,flag_emoji')
            ->withCount(['media as media_count' => $mediaCountFilter])
            ->whereIn('status', [Event::STATUS_LIVE, Event::STATUS_ANNOUNCED])
            ->when($request->string('location')->isNotEmpty(), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'ILIKE', '%'.$request->string('location').'%')
                        ->orWhere('location', 'ILIKE', '%'.$request->string('location').'%');
                });
            })
            ->when(! empty($countryCodes), function ($query) use ($countryCodes) {
                $query->whereHas('country', fn ($q) => $q->whereIn('code', $countryCodes));
            })
            ->when($photographerId, fn ($query) => $query->whereHas('media', fn ($m) => $m->where('photographer_id', $photographerId)))
            ->when($type, fn ($query) => $query->whereHas('media', fn ($m) => $m->where('type', $type)))
            ->withCoverThumbnail()
            ->orderByDesc('starts_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'location' => $event->location,
                'slug' => $event->slug,
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status,
                'media_count' => $event->media_count,
                'cover_thumbnail_s3_key' => $event->cover_thumbnail_s3_key,
                'country' => $event->country ? [
                    'code' => $event->country->code,
                    'name' => $event->country->name,
                    'flag_emoji' => $event->country->flag_emoji,
                ] : null,
            ]),
        ]);
    }

    /**
     * Esemeny media listaja lapozva (a galeria oldal infinite scroll-jahoz,
     * a masodik oldaltol kezdve — az elso oldal SEO miatt Inertia-n at, szerver
     * oldalon renderelodik, lasd Public\EventController::show()).
     */
    public function media(Request $request, Event $event): JsonResponse
    {
        $type = $request->string('type')->value();
        $shotFrom = $request->string('shot_from')->value();
        $shotTo = $request->string('shot_to')->value();

        $mediaQuery = $event->media()
            ->when($this->visibility->attributionPublic(), fn ($q) => $q->with('photographer:id,name'))
            ->where('status', Media::STATUS_READY)
            ->when(in_array($type, ['photo', 'video'], true), fn ($q) => $q->where('type', $type))
            ->when(filled($shotFrom), fn ($q) => $q->whereTime('shot_at', '>=', $shotFrom))
            ->when(filled($shotTo), fn ($q) => $q->whereTime('shot_at', '<=', $shotTo))
            ->orderBy('shot_at');

        $page = $mediaQuery->paginate(24)->withQueryString();

        return response()->json([
            'data' => $page->through(fn (Media $m) => (new MediaResource($m))->resolve())->items(),
            'next_page_url' => $page->nextPageUrl(),
            'current_page' => $page->currentPage(),
        ]);
    }

    /**
     * "Ebben az időpontban készült képek" (EPIC-17): ugyanabbol az esemenybol a
     * megadott media-hoz idoben kozeli (±window_minutes) felvetelek — noveli az
     * egy rendelesen beluli media szamot.
     */
    public function nearby(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'media_id' => ['required', 'integer'],
            'window_minutes' => ['nullable', 'integer', 'min:1', 'max:30'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $reference = $event->media()->whereKey($data['media_id'])->first();

        if (! $reference || ! $reference->shot_at) {
            return response()->json(['data' => []]);
        }

        $window = (int) ($data['window_minutes'] ?? 3);
        $limit = (int) ($data['limit'] ?? 6);

        $from = $reference->shot_at->copy()->subMinutes($window);
        $to = $reference->shot_at->copy()->addMinutes($window);

        $media = $event->media()
            ->when($this->visibility->attributionPublic(), fn ($q) => $q->with('photographer:id,name'))
            ->where('status', Media::STATUS_READY)
            ->whereKeyNot($reference->id)
            ->whereBetween('shot_at', [$from, $to])
            ->orderByRaw('ABS(EXTRACT(EPOCH FROM (shot_at - ?::timestamptz)))', [$reference->shot_at->toIso8601String()])
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $media->map(fn (Media $m) => (new MediaResource($m))->resolve())->all(),
        ]);
    }

    /**
     * Idő-hisztogram az esemeny galeriahoz (EPIC-17): orankent hany kesz felvetel
     * keszult — a galeria tetejen egy mini bargraph ehhez, kattintasra az adott orara ugrik.
     */
    public function histogram(Event $event): JsonResponse
    {
        $rows = $event->media()
            ->where('status', Media::STATUS_READY)
            ->whereNotNull('shot_at')
            ->selectRaw('EXTRACT(HOUR FROM shot_at)::int as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => ['hour' => (int) $row->hour, 'count' => (int) $row->count])->all(),
        ]);
    }

    /**
     * Egyedi helyszin lista (a fooldali kereso Helyszin mezojehez), opcionalisan
     * orszag(ok)ra szurve — ha tobb orszagot valaszt a felhasznalo, csak az ott
     * levo varosok/helyszinek jelennek meg.
     */
    public function locations(Request $request): JsonResponse
    {
        $countryCodes = array_filter((array) $request->query('countries', []));

        $locations = Event::query()
            ->join('countries', 'countries.id', '=', 'events.country_id')
            ->whereIn('events.status', [Event::STATUS_LIVE, Event::STATUS_ANNOUNCED])
            ->when(! empty($countryCodes), function ($query) use ($countryCodes) {
                $query->whereIn('countries.code', $countryCodes);
            })
            ->distinct()
            ->orderBy('events.location')
            ->get([
                'events.location',
                'countries.code as country_code',
                'countries.flag_emoji as country_flag',
            ]);

        return response()->json([
            'data' => $locations->map(fn ($row) => [
                'location' => $row->location,
                'country_code' => $row->country_code,
                'country_flag' => $row->country_flag,
            ]),
        ]);
    }
}
