<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kozos esemeny-kereso logika a nyilvanos oldalakhoz (fooldal, /events) es a
 * megfelelo API vegpontokhoz — igy a ket helyen (Inertia oldal + JSON API)
 * nem duplikalodik a szures.
 */
class EventSearch
{
    /**
     * @param  array{location?: string, countries?: array<string>, date_from?: string, date_to?: string, type?: string, photographer_id?: string, lat?: float, lon?: float, radius?: float}  $filters
     * @return Builder<Event>
     */
    public function query(array $filters): Builder
    {
        $query = Event::query()
            ->with('country:id,code,name_hu,name_en,flag_emoji')
            ->withCount(['media as media_count' => fn ($q) => $q->where('status', Media::STATUS_READY)])
            ->withCoverThumbnail()
            ->whereIn('status', [Event::STATUS_LIVE, Event::STATUS_ANNOUNCED]);

        if (filled($filters['location'] ?? null)) {
            $location = $filters['location'];
            $query->where(function ($q) use ($location) {
                $q->where('name', 'ILIKE', "%{$location}%")
                    ->orWhere('location', 'ILIKE', "%{$location}%");
            });
        }

        if (! empty($filters['countries'] ?? [])) {
            $query->whereHas('country', fn ($q) => $q->whereIn('code', $filters['countries']));
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('event_date', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('event_date', '<=', $filters['date_to']);
        }

        if (in_array($filters['type'] ?? 'all', ['photo', 'video'], true)) {
            $query->whereHas('media', fn ($q) => $q
                ->where('type', $filters['type'])
                ->where('status', Media::STATUS_READY));
        }

        if (filled($filters['photographer_id'] ?? null)) {
            $query->whereHas('media', fn ($q) => $q
                ->where('photographer_id', $filters['photographer_id'])
                ->where('status', Media::STATUS_READY));
        }

        if (filled($filters['lat'] ?? null) && filled($filters['lon'] ?? null)) {
            $lat = (float) $filters['lat'];
            $lon = (float) $filters['lon'];
            $radiusMeters = (float) ($filters['radius'] ?? 15) * 1000;

            $query->whereRaw(
                'ST_DWithin(ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [$lon, $lat, $radiusMeters],
            );

            $query->selectRaw(
                'ST_Distance(ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) / 1000 as distance_km',
                [$lon, $lat],
            )->orderBy('distance_km');
        }

        return $query;
    }

    /**
     * Csak akkor hasznaljuk a tavolsag szerinti rendezest, ha GPS-szures aktiv —
     * kulonben a friss esemenyek kerulnek elore.
     */
    public function defaultOrder(Builder $query, bool $hasGpsFilter): Builder
    {
        return $hasGpsFilter ? $query : $query->orderByDesc('starts_at');
    }
}
