<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Karbantartási mód kapcsoló (/admin/settings/maintenance, superadmin). A
 * publikus oldalt egy „hamarosan" lap mögé teszi; az admin, a bejelentkezés és
 * a fizetési webhookok elérhetők maradnak.
 */
class MaintenanceModeController extends Controller
{
    public function index(MaintenanceMode $maintenance): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Maintenance', [
            'settings' => $maintenance->toArray(),
        ]);
    }

    public function update(Request $request, MaintenanceMode $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $maintenance->update($data['enabled'], $data['message'] ?? null);

        return back()->with('success', $data['enabled']
            ? 'Karbantartási mód BEKAPCSOLVA — a látogatók a „hamarosan" lapot látják.'
            : 'Karbantartási mód kikapcsolva — az oldal újra nyilvános.');
    }
}
