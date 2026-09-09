<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AnimationSettings;
use App\Services\GeoSearchSettings;
use App\Services\MediaStorage;
use App\Services\PhotographerVisibility;
use App\Services\Seo;
use App\Services\SiteBranding;
use App\Services\SocialLinks;
use App\Services\ThemeSettings;
use App\Support\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => app(SiteBranding::class)->name(),
            'branding' => app(SiteBranding::class)->toArray(),
            // Kitöltött közösségi média linkek (lábléc + Kapcsolat oldal).
            'social' => fn () => app(SocialLinks::class)->all(),
            // SEO meta az aktuális oldalhoz — SPA-navigációnál a <SeoHead> ebből
            // frissíti a canonical / description / OG tageket (első lefestésnél a
            // blade `$seo` composer csinálja ugyanezt).
            'seo' => fn () => app(Seo::class)->forRequest($request),
            // A publikus média böngészőből elérhető bázis-URL-je: dev `/storage`,
            // élesben az R2 publikus domain. A frontend `mediaUrl()` helper ezt fűzi a kulcs elé.
            'mediaBaseUrl' => MediaStorage::publicBaseUrl(),
            // GPS sugaras helyszín-keresés (PostGIS-igényes) — a kereső-panel + a
            // térkép-modal ez alapján mutatja/rejti a GPS fület és a „Keress itt" gombot.
            'geoSearch' => fn () => app(GeoSearchSettings::class)->enabled(),
            'photographerSearch' => fn () => app(PhotographerVisibility::class)->attributionPublic(),
            'auth' => [
                'user' => $request->user()
                    ? $request->user()->only('id', 'name', 'email', 'role')
                    : null,
            ],
            // A csapat-felület oldalsáv menüje (szekciókba rendezve, magyarázatokkal).
            // Csak bejelentkezett csapattagnak; a látogatói oldalakon null.
            'adminNav' => fn () => $request->user() && in_array($request->user()->role, [
                User::ROLE_SUPERADMIN, User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER, User::ROLE_ORGANIZER,
            ], true) ? AdminNavigation::sections($request->user()) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'themeMode' => app(ThemeSettings::class)->mode(),
            // Mozgás / animáció beállítások (a frontend reveal / számláló / hero ebből dönt).
            'animation' => fn () => app(AnimationSettings::class)->toArray(),
            'locale' => App::getLocale(),
            'translations' => fn () => $this->translations(App::getLocale()),
        ];
    }

    /**
     * A `lang/{locale}.json` teljes tartalma — a frontend `t()` helper ebbol
     * olvas (nincs kulon vue-i18n csomag).
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }
}
