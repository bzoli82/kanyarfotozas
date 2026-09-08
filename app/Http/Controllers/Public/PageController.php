<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\BulkDiscount;
use App\Services\LegalPages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Statikus informacios aloldalak (EPIC-11): Rolunk, Adatvedelem, Arak.
 * A FAQ es a Kapcsolat kulon controllerben van (dinamikus adat / urlap).
 */
class PageController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('Info/About');
    }

    public function privacy(): Response
    {
        return Inertia::render('Info/Privacy');
    }

    public function shop(BulkDiscount $bulkDiscount): Response
    {
        return Inertia::render('Info/Shop', [
            'basePrice' => (int) SiteSetting::get('base_price_huf', 1490),
            'bulkTiers' => $bulkDiscount->tiers(),
        ]);
    }

    public function impressum(LegalPages $legal): Response
    {
        return Inertia::render('Info/Impressum', [
            'bodyHtml' => $legal->impressumHtml(),
        ]);
    }

    public function terms(LegalPages $legal): Response
    {
        return Inertia::render('Info/Terms', [
            'bodyHtml' => $legal->termsHtml(),
            'updatedAt' => $legal->termsUpdatedAt(),
        ]);
    }
}
