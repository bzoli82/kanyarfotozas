<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportSettingsController extends Controller
{
    /**
     * A fotos sajat e-mail riport beallitasai (heti/havi ertesito) — EPIC-12.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_weekly' => ['required', 'boolean'],
            'report_monthly' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill($data)->save();

        return back()->with('success', 'E-mail riport beállítások mentve.');
    }
}
