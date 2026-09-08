<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionShare;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kollekció / wishlist (EPIC-17) kliens-oldali vegpontjai. A kollekcio maga
 * localStorage-ban el; ez a ket vegpont a wishlist-szamlalot noveli (admin
 * statisztikahoz) es megoszthato linket general.
 */
class CollectionController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['media_id' => ['required', 'integer']])['media_id'];

        Media::query()->whereKey($id)->where('status', Media::STATUS_READY)->increment('wishlist_count');

        return response()->json(status: 204);
    }

    public function share(Request $request): JsonResponse
    {
        $data = $request->validate([
            'media_ids' => ['required', 'array', 'min:1', 'max:100'],
            'media_ids.*' => ['integer'],
        ]);

        $validIds = Media::query()
            ->whereIn('id', $data['media_ids'])
            ->where('status', Media::STATUS_READY)
            ->pluck('id')
            ->values();

        if ($validIds->isEmpty()) {
            return response()->json(['message' => 'A kollekció üres vagy már nem elérhető.'], 422);
        }

        $share = CollectionShare::query()->create(['media_ids' => $validIds->all()]);

        return response()->json([
            'url' => route('public.collection.shared', $share->share_token),
            'token' => $share->share_token,
            'expires_at' => $share->expires_at->toIso8601String(),
        ]);
    }
}
