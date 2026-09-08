<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Az oldal neve / márkajele (/admin/settings/branding) — csak superadmin.
 * Egy helyen szerkeszthető: a fejléc logó, az oldalcímek, az e-mailek és a
 * vízjel szövege is innen jön (a végleges név még nincs eldöntve).
 */
class BrandingSettingsController extends Controller
{
    public function index(SiteBranding $branding): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Branding', [
            'branding' => $branding->toArray(),
            'watermarkPreview' => $branding->watermarkText(),
        ]);
    }

    public function update(Request $request, SiteBranding $branding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'logo_lead' => ['required', 'string', 'max:30'],
            'logo_tail' => ['nullable', 'string', 'max:30'],
        ]);

        $branding->update($data['name'], $data['logo_lead'], $data['logo_tail'] ?? '');

        return back()->with('success', 'Az oldal neve elmentve — a vízjel is frissült.');
    }
}
