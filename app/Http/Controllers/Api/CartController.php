<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * A kliens oldalon (localStorage/Pinia) tarolt media ID-khoz visszaadja az
     * AKTUALIS allapotot/arat — a /cart oldal ezzel frissiti a megjelenitett
     * kosarat, hogy sose elavult vagy mar torolt/elrejtett media latszodjon.
     */
    public function show(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));

        $media = Media::query()
            ->whereIn('id', $ids)
            ->where('status', Media::STATUS_READY)
            ->with('event:id,name,slug,location')
            ->get();

        return response()->json([
            'data' => $media->map(fn (Media $m) => (new MediaResource($m))->resolve())->values(),
        ]);
    }
}
