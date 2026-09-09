<?php

use App\Http\Middleware\ApplyPaymentSettings;
use App\Http\Middleware\EnsureTwoFactorIsSetUp;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Services\ErrorReporter;
use App\Services\TwoFactor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Éles környezetben egy fordított proxy (Coolify/Traefik, Forge/nginx, CDN) áll
        // az app előtt — enélkül a Laravel http-nek látná a https kérést (rossz signed
        // URL / redirect / mixed content) és a proxy IP-jét venné kliens IP-nek (rate
        // limit, failed-login hash, forensic ujjlenyomat). Lokálisan nincs proxy → hatástalan.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'payment.settings' => ApplyPaymentSettings::class,
            '2fa' => EnsureTwoFactorIsSetUp::class,
        ]);

        // A „megbízható eszköz" süti már saját maga Crypt-titkosított + hitelesített
        // (App\Services\TwoFactor::trustedDeviceToken), így a keret dupla-titkosítása felesleges.
        $middleware->encryptCookies(except: [TwoFactor::TRUSTED_COOKIE]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn ($request) => $request->user()?->isAdmin() ? '/admin/dashboard' : '/photographer/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Kezeletlen kivételek rögzítése + értesítés (App\Services\ErrorReporter).
        $exceptions->report(function (Throwable $e): void {
            app(ErrorReporter::class)->report($e);
        });

        // Publikus hibaoldalak — Inertia „Error" komponens a nyers Symfony hibalap helyett.
        // A böngészőben megnyitott (nem JSON, nem Inertia-XHR) kérésekre. Az 500/503-at
        // helyi fejlesztésben nem vesszük át (kell a részletes stacktrace).
        $exceptions->respond(function (HttpResponse $response, Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->header('X-Inertia')) {
                return $response;
            }

            $status = $response->getStatusCode();
            $handled = [403, 404, 419, 429, 500, 503];

            if (! in_array($status, $handled, true)) {
                return $response;
            }

            if (in_array($status, [500, 503], true) && app()->environment('local')) {
                return $response;
            }

            // A route-model-binding (pl. `/events/{event:slug}`) a SubstituteBindings
            // middleware-ben bukhat el, ami ELŐBB fut, mint a SetLocale / HandleInertiaRequests —
            // ilyenkor a locale és a megosztott propok (fordítások, branding) még nincsenek
            // beállítva, és a hibaoldal nyers `t()` kulcsokat mutatna. Kézzel pótoljuk.
            $locale = rescue(fn () => $request->session()->get('app_locale'), null, false);
            if (in_array($locale, SetLocale::SUPPORTED, true)) {
                app()->setLocale($locale);
            }
            $shared = rescue(fn () => app(HandleInertiaRequests::class)->share($request), [], false);

            return Inertia::render('Error', [...$shared, 'status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
