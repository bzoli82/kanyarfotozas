<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SocialLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Közösségi média elérhetőségek (/admin/settings/social) — csak superadmin.
 * A kitöltött linkek megjelennek a publikus láblécben és a Kapcsolat oldalon.
 */
class SocialSettingsController extends Controller
{
    public function index(SocialLinks $social): Response
    {
        return Inertia::render('Admin/Settings/Social', [
            'social' => $social->forForm(),
        ]);
    }

    public function update(Request $request, SocialLinks $social): RedirectResponse
    {
        $data = $request->validate([
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
        ]);

        $social->update($data);

        return back()->with('success', 'Közösségi média linkek elmentve.');
    }
}
