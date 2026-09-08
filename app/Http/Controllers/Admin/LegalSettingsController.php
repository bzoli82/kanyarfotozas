<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LegalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impresszum + ÁSZF szerkesztő (superadmin, /admin/settings/legal).
 * A tartalom Markdown, a `site_settings`-ben tárolva (App\Services\LegalPages).
 */
class LegalSettingsController extends Controller
{
    public function index(LegalPages $legal): Response
    {
        return Inertia::render('Admin/Settings/Legal', [
            'legal' => $legal->forForm(),
        ]);
    }

    public function update(Request $request, LegalPages $legal): RedirectResponse
    {
        $data = $request->validate([
            'impressum' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:60000'],
        ]);

        $legal->update($data);

        return back()->with('success', 'Jogi oldalak elmentve.');
    }
}
