<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\CaptchaSettings;
use App\Services\PhotographerVisibility;
use App\Support\FormGuard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    /**
     * Media reszlet oldal (/media/{media}) — nagy elonezet, ar, kosarba gomb.
     */
    public function show(Request $request, Media $media, FormGuard $guard, CaptchaSettings $captcha, PhotographerVisibility $visibility): Response
    {
        abort_unless($media->isReady(), 404);

        $attributionPublic = $visibility->attributionPublic();
        $media->load(array_filter(['event.country', $attributionPublic ? 'photographer' : null]));

        $nearby = Media::query()
            ->where('event_id', $media->event_id)
            ->where('id', '!=', $media->id)
            ->where('status', Media::STATUS_READY)
            ->when($media->shot_at, fn ($q) => $q->whereBetween('shot_at', [
                $media->shot_at->clone()->subMinutes(3),
                $media->shot_at->clone()->addMinutes(3),
            ]))
            ->orderBy('shot_at')
            ->limit(6)
            ->get();

        return Inertia::render('Media/Show', [
            'media' => (new MediaResource($media))->resolve(),
            'event' => [
                'id' => $media->event->id,
                'name' => $media->event->name,
                'slug' => $media->event->slug,
                'location' => $media->event->location,
                'country' => $media->event->country ? [
                    'name' => $media->event->country->name,
                    'flag_emoji' => $media->event->country->flag_emoji,
                ] : null,
            ],
            'photographer' => $attributionPublic && $media->photographer ? [
                'id' => $media->photographer->id,
                'name' => $media->photographer->name,
            ] : null,
            'nearby' => $nearby->map(fn (Media $m) => (new MediaResource($m))->resolve())->values(),
            'contactGuard' => $media->photographer_id ? $guard->issue() : null,
            'contactHcaptcha' => $media->photographer_id ? $captcha->forView() : null,
        ]);
    }
}
