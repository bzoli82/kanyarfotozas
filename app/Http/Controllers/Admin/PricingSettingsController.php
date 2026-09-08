<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\BulkDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Árazás (superadmin) — az alap médiaár + az automatikus mennyiségi
 * kedvezmény-sávok (App\Services\BulkDiscount).
 */
class PricingSettingsController extends Controller
{
    public function index(BulkDiscount $bulkDiscount): Response
    {
        return Inertia::render('Admin/Settings/Pricing', [
            'basePrice' => (int) SiteSetting::get('base_price_huf', 1490),
            'tiers' => $bulkDiscount->tiers(),
        ]);
    }

    public function update(Request $request, BulkDiscount $bulkDiscount): RedirectResponse
    {
        $data = $request->validate([
            'base_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'tiers' => ['present', 'array', 'max:6'],
            'tiers.*.min' => ['required', 'integer', 'min:2', 'max:1000'],
            'tiers.*.percent' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        SiteSetting::set('base_price_huf', (string) $data['base_price']);
        $bulkDiscount->update($data['tiers']);

        return back()->with('success', 'Árazás elmentve.');
    }
}
