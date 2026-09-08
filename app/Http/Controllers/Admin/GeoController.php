<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GEO / AI-kereshetőség beállítások (superadmin). Ld. App\Services\GeoSettings.
 */
class GeoController extends Controller
{
    public function index(GeoSettings $geo): Response
    {
        return Inertia::render('Admin/Settings/Geo', [
            'enabled' => $geo->enabled(),
            'description' => $geo->description(),
            'defaultDescription' => $geo->defaultDescription(),
        ]);
    }

    public function update(Request $request, GeoSettings $geo): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $geo->update($data['enabled'], (string) ($data['description'] ?? ''));
        Cache::forget('llms.txt');

        return back()->with('success', 'GEO beállítások mentve.');
    }
}
