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

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
