<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\MaintenanceMode;
use App\Services\SiteBranding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Karbantartási mód: bekapcsolva a látogatók egy „hamarosan" lapot látnak (503),
 * de az admin, a bejelentkezés, a meghívó-elfogadás és a fizetési webhookok
 * (`/api/*`) elérhetők maradnak. Bejelentkezett csapattag mindig átjut.
 */
class CheckMaintenanceMode
{
    /** Karbantartás alatt is elérhető útvonal-prefixek. */
    private const ALLOWED = ['admin', 'login', 'logout', 'two-factor-challenge', 'api', 'invitations', 'storage', 'build', 'up', 'locale', 'sw.js', 'manifest.webmanifest'];

    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = app(MaintenanceMode::class);

        if (! $maintenance->enabled()) {
            return $next($request);
        }

        if ($request->user() && in_array($request->user()->role, [
            User::ROLE_SUPERADMIN, User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER, User::ROLE_ORGANIZER,
        ], true)) {
            return $next($request);
        }

        if (in_array($request->segment(1), self::ALLOWED, true)) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => $maintenance->message(),
            'brand' => app(SiteBranding::class)->name(),
        ], 503)->header('Retry-After', '3600');
    }
}
