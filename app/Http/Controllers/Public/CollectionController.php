<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\CollectionShare;
use App\Models\Media;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kollekció / wishlist oldal (EPIC-17). A `/collection` a latogato sajat,
 * localStorage-ban tarolt kollekcioja (a kliens tolti fel). A
 * `/collection/share/{token}` egy masok altal megoszthato, 7 napig elo snapshot.
 */
class CollectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/Collection', [
            'shared' => null,
        ]);
    }

    public function shared(string $token): Response
    {
        $share = CollectionShare::query()->where('share_token', $token)->first();

        abort_if($share === null || $share->isExpired(), 404);

        $share->increment('view_count');

        $media = Media::query()
            ->whereIn('id', $share->media_ids)
            ->where('status', Media::STATUS_READY)
            ->with('event:id,name,slug,location')
            ->get()
            ->sortBy(fn (Media $m) => array_search($m->id, $share->media_ids, true))
            ->values();

        return Inertia::render('Public/Collection', [
            'shared' => [
                'token' => $share->share_token,
                'expires_at' => $share->expires_at->toIso8601String(),
                'items' => $media->map(fn (Media $m) => (new MediaResource($m))->resolve())->all(),
            ],
        ]);
    }
}
