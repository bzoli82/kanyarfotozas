<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhotographerVisibility;
use Illuminate\Http\JsonResponse;

class PhotographerController extends Controller
{
    /**
     * Nyilvanos fotos lista a fooldali keresopanelhez (csak akik hozzajarultak
     * a nyilvanos megjeleneshez es aktivak — users.is_public). Ha a superadmin
     * kikapcsolta a fotos-attribuciot, ures listat ad (nincs fotos-szuro).
     */
    public function index(PhotographerVisibility $visibility): JsonResponse
    {
        if (! $visibility->attributionPublic()) {
            return response()->json(['data' => []]);
        }

        $photographers = User::query()
            ->where('role', User::ROLE_PHOTOGRAPHER)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $photographers,
        ]);
    }
}
