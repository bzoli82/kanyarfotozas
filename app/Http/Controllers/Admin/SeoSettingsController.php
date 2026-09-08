<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Publikus SEO-beállítások (/admin/settings/seo, csak superadmin): alap
 * meta-leírás, cím-kiegészítés, OG-kép, Google Search Console azonosító és a
 * globális „kereshetőség" kapcsoló. A tényleges meta-generálás az
 * `App\Services\Seo`-ban van — ez csak a `site_settings` értékeit írja.
 */
class SeoSettingsController extends Controller
{
    public const OG_IMAGE_MAX_KB = 8192;

    public function index(SeoSettings $seo): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Seo', [
            'settings' => $seo->toArray(),
            'defaults' => [
                'description' => SeoSettings::DEFAULT_DESCRIPTION,
                'title_suffix' => SeoSettings::DEFAULT_TITLE_SUFFIX,
            ],
            'urls' => [
                'sitemap' => route('sitemap'),
                'robots' => route('robots'),
            ],
            'ogImageMaxMb' => (int) round(self::OG_IMAGE_MAX_KB / 1024),
        ]);
    }

    public function update(Request $request, SeoSettings $seo, ImageProcessingService $images): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:320'],
            'title_suffix' => ['nullable', 'string', 'max:120'],
            'search_visible' => ['required', 'boolean'],
            'google_verification' => ['nullable', 'string', 'max:200'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::OG_IMAGE_MAX_KB, 'dimensions:min_width=600,min_height=315'],
            'remove_og_image' => ['sometimes', 'boolean'],
        ]);

        $seo->update($data);

        $disk = Storage::disk(MediaStorage::public());

        if ($request->boolean('remove_og_image') && $seo->ogImagePath()) {
            $disk->delete($seo->ogImagePath());
            $seo->setOgImagePath(null);
        }

        if ($request->hasFile('og_image')) {
            if ($seo->ogImagePath()) {
                $disk->delete($seo->ogImagePath());
            }

            $hero = $images->makeHeroImage($request->file('og_image')->getRealPath());
            $key = 'seo/og-'.Str::lower(Str::random(12)).'.webp';
            $disk->put($key, $hero['binary']);
            $seo->setOgImagePath($key);
        }

        return back()->with('success', 'SEO-beállítások elmentve.');
    }
}
