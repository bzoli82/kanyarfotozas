<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ha a superadmin kötelezővé tette a 2FA-t (`two_factor_required`), akkor minden
 * admin/superadmin fiók, amelyiken még nincs élesítve, a biztonsági beállítások
 * oldalra irányítódik, amíg be nem állítja. A beállító oldal + a kijelentkezés
 * kivétel (különben nem tudná megcsinálni / kilépni).
 */
class EnsureTwoFactorIsSetUp
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('admin.settings.security', 'admin.settings.security.*', 'logout');

        if (
            $user
            && $user->isAdmin()
            && ! $exempt
            && $this->twoFactor->isRequiredForAdmins()
            && ! $user->hasTwoFactorEnabled()
        ) {
            return redirect()->route('admin.settings.security')
                ->with('error', 'A kétfaktoros hitelesítés kötelező — állítsd be a folytatáshoz.');
        }

        return $next($request);
    }
}
