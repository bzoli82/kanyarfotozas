<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\R2Storage;
use App\Services\Seo;
use App\Services\SiteBranding;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Az e-mail kuldes (SMTP) beallitasait a site_settings-bol a config-ba
        // toltjuk — a superadmin a /admin/settings/critical oldalon allitja, nem
        // kell .env-et szerkeszteni a szerveren. Migracio elott / DB nelkul csendes.
        rescue(fn () => app(MailSettings::class)->applyRuntimeConfig(), report: false);

        // Ugyanígy a Cloudflare R2 (tárhely) kulcsai — a superadmin a
        // /admin/settings/storage oldalon állítja, nem kell .env.
        rescue(fn () => app(R2Storage::class)->applyRuntimeConfig(), report: false);

        // Az oldal neve (SiteBranding) elerheto az app.blade.php cimeben es a mail
        // sablonok alairasaban — a config('app.name') helyett. View composer, hogy
        // rendereleskor (ne boot-kor) olvassa, es migracio elott se dobjon.
        View::composer(['app', 'mail.*'], function ($view) {
            try {
                $view->with('brandName', app(SiteBranding::class)->name());
            } catch (\Throwable) {
                $view->with('brandName', SiteBranding::DEFAULT_NAME);
            }
        });

        // SEO meta (title/description/canonical/OG/Twitter/robots) az első lefestéshez
        // és a JS-t nem futtató közösségi link-preview botokhoz. SPA-navigációnál a
        // HandleInertiaRequests `seo` shared propja frissíti ugyanezt.
        View::composer('app', function ($view) {
            try {
                $view->with('seo', app(Seo::class)->forRequest(request()));
            } catch (\Throwable) {
                $view->with('seo', null);
            }
        });

        // A publikus űrlapok (Kapcsolat, „Kérdés a fotóshoz") rétegzett rate limitje:
        // IP-nként 3/perc + e-mail-címenként 5/óra + globálisan 60/óra (elosztott
        // spam-kampány ellen). A FormGuard time-trap + proof-of-work mellé.
        RateLimiter::for('contact', fn (Request $request) => array_values(array_filter([
            Limit::perMinute(3)->by('contact-ip:'.$request->ip()),
            filled($request->input('email'))
                ? Limit::perHour(5)->by('contact-email:'.sha1(mb_strtolower(trim((string) $request->input('email')))))
                : null,
            Limit::perHour(60)->by('contact-global'),
        ])));

        // Esemeny letrehozasa/szerkesztese/torlese: admin/superadmin barmelyiket kezelheti.
        // Fotos uj esemenyt barki letrehozhat (leendo esemenynel $event=null), de meglevot
        // csak akkor szerkeszthet/torolhet, ha o hozta letre (created_by).
        Gate::define('manage-event', function (User $user, ?Event $event = null) {
            if ($user->isAdmin()) {
                return true;
            }

            if ($user->isPhotographer()) {
                return $event === null || $event->created_by === $user->id;
            }

            return false;
        });
    }
}
