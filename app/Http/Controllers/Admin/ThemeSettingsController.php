<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ThemeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ThemeSettingsController extends Controller
{
    /**
     * Megjelenes-beallitasok (sema, alapertelmezett mod, akcentszin, sarok-lekerekites,
     * betutipus) — csak superadmin, minden publikus/admin oldalra hat (app.blade.php
     * injektalja CSS custom property-kkent).
     */
    public function index(ThemeSettings $theme): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Theme', [
            'settings' => $theme->toArray(),
            'modes' => [
                ['value' => 'dark', 'label' => 'Sötét'],
                ['value' => 'light', 'label' => 'Világos'],
                ['value' => 'system', 'label' => 'Rendszer szerint'],
            ],
            'fonts' => collect(ThemeSettings::FONTS)->keys()->map(fn ($key) => ['value' => $key, 'label' => $key])->values(),
            'paletteKeys' => [
                ['key' => 'surface_0', 'label' => 'Háttér'],
                ['key' => 'surface_1', 'label' => 'Kártya / panel'],
                ['key' => 'surface_2', 'label' => 'Kiemelt felület'],
                ['key' => 'border', 'label' => 'Keret'],
                ['key' => 'content', 'label' => 'Szöveg'],
                ['key' => 'muted', 'label' => 'Halvány szöveg'],
            ],
        ]);
    }

    public function update(Request $request, ThemeSettings $theme): RedirectResponse
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        $data = $request->validate([
            'preset' => ['required', Rule::in(ThemeSettings::presetKeys())],
            'mode' => ['required', Rule::in(ThemeSettings::MODES)],
            'accent_color' => ['required', 'string', $hex],
            'border_radius' => ['required', 'integer', 'min:0', 'max:32'],
            'font_family' => ['required', Rule::in(array_keys(ThemeSettings::FONTS))],
            'custom' => ['sometimes', 'array'],
            'custom.light' => ['sometimes', 'array'],
            'custom.dark' => ['sometimes', 'array'],
            'custom.light.*' => ['string', $hex],
            'custom.dark.*' => ['string', $hex],
        ]);

        $theme->update($data);

        return back()->with('success', 'Téma beállítások elmentve.');
    }
}
