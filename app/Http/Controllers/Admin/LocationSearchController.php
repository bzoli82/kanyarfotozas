<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeoSearchSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Helyszín-keresés (GPS sugaras szűrés) kapcsoló + magyarázat (superadmin).
 * Ld. App\Services\GeoSearchSettings.
 */
class LocationSearchController extends Controller
{
    public function index(GeoSearchSettings $settings): Response
    {
        return Inertia::render('Admin/Settings/LocationSearch', [
            'enabled' => $settings->enabled(),
        ]);
    }

    public function update(Request $request, GeoSearchSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $settings->update($data['enabled']);

        return back()->with('success', $data['enabled']
            ? 'GPS sugaras keresés bekapcsolva. Ellenőrizd, hogy az adatbázison elérhető a PostGIS.'
            : 'GPS sugaras keresés kikapcsolva.');
    }
}
