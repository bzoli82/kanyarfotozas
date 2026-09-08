<?php

namespace App\Http\Middleware;

use App\Services\PaymentSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fizetési szolgáltatók hitelesítő adatait a `site_settings`-ből a config-ba
 * tölti — a checkout / visszatérés / webhook / IPN route-okon, mielőtt bármelyik
 * gateway kiolvasná a `config('services.stripe.*' / 'services.simplepay.*')`-t.
 */
class ApplyPaymentSettings
{
    public function __construct(private PaymentSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->applyRuntimeConfig();

        return $next($request);
    }
}
