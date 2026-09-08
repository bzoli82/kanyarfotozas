<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vizjeles media megosztas (EPIC-17). A `store` a letoltesi oldalrol hivodik
 * (ervenyes download tokennel bizonyitva a vasarlast) es egy 30 napos publikus
 * linket ad vissza. A `show` a nyilvanos /share/{token} oldal — vizjeles media
 * + esemeny adatok + "Megvásárolom" CTA.
 */
class MediaShareController extends Controller
{
    public function store(Request $request, Media $media, MediaShareService $shares): JsonResponse
    {
        $data = $request->validate([
            'download_token' => ['required', 'uuid'],
            'platform' => ['nullable', 'string', 'in:instagram,facebook,tiktok,link'],
        ]);

        $share = $shares->createFor($media, $data['download_token'], $data['platform'] ?? null);

        if (! $share) {
            return response()->json(['message' => 'A megosztáshoz érvényes vásárlás szükséges.'], 403);
        }

        return response()->json([
            'url' => route('public.share', $share->share_token),
            'expires_at' => $share->expires_at->toIso8601String(),
        ]);
    }

    public function show(string $token, MediaShareService $shares): Response
    {
        $share = $shares->resolve($token);

        abort_if($share === null, 404);

        $media = $share->media;

        return Inertia::render('Public/SharedMedia', [
            'media' => [
                'id' => $media->id,
                'type' => $media->type,
                'watermarked_s3_key' => $media->watermarked_s3_key,
                'hls_playlist_s3_key' => $media->hls_playlist_s3_key,
                'thumbnail_s3_key' => $media->thumbnail_s3_key,
                'price_cents' => $media->price_cents,
                'duration_seconds' => $media->duration_seconds,
            ],
            'event' => $media->event ? [
                'name' => $media->event->name,
                'slug' => $media->event->slug,
                'location' => $media->event->location,
            ] : null,
            'photographer' => $media->photographer?->name,
            'mediaUrl' => route('public.media.show', $media->id),
        ]);
    }
}
