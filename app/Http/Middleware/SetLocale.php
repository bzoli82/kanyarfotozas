<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * A latogatoi nyelvvalasztas (HU/EN) a session-ben el; ez a middleware minden
 * web keresre beallitja a Laravel locale-t. Alapertelmezes: config('app.locale').
 */
class SetLocale
{
    public const SUPPORTED = ['hu', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('app_locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
