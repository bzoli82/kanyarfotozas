<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Kétfaktoros kihívás: a helyes jelszó után, a bejelentkezés befejezése előtt.
 * A függőben lévő felhasználó a `login.2fa` munkamenet-kulcsban van (max 5 percig).
 */
class TwoFactorChallengeController extends Controller
{
    private const SESSION_TTL = 300;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactor $twoFactor) {}

    public function create(Request $request): InertiaResponse|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.2fa');
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'A bejelentkezés lejárt — kezdd elölről.']);
        }

        if (now()->timestamp - (int) ($pending['at'] ?? 0) > self::SESSION_TTL) {
            $request->session()->forget('login.2fa');

            return redirect()->route('login')->withErrors(['email' => 'A bejelentkezés lejárt — kezdd elölről.']);
        }

        $key = 'two-factor:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Túl sok sikertelen kód. Próbáld újra '.RateLimiter::availableIn($key).' másodperc múlva.',
            ]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        $verified = $this->attemptVerification($user, $data);

        if (! $verified) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages([
                'code' => filled($data['recovery_code'] ?? null) ? 'Érvénytelen helyreállító kód.' : 'Érvénytelen kód.',
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) ($pending['remember'] ?? false);
        $request->session()->forget('login.2fa');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $redirect = redirect()->intended($user->isAdmin() ? '/admin/dashboard' : '/photographer/dashboard');

        if (! empty($data['remember_device'])) {
            $redirect = $redirect->withCookie(cookie(
                TwoFactor::TRUSTED_COOKIE,
                $this->twoFactor->trustedDeviceToken($user),
                TwoFactor::TRUSTED_DAYS * 24 * 60,
            ));
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attemptVerification(User $user, array $data): bool
    {
        if (filled($data['recovery_code'] ?? null)) {
            return $this->twoFactor->consumeRecoveryCode($user, (string) $data['recovery_code']);
        }

        if (filled($data['code'] ?? null)) {
            $secret = $this->twoFactor->secretFor($user);

            return $secret !== null && $this->twoFactor->verify($secret, (string) $data['code']);
        }

        return false;
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login.2fa.id');

        return $id ? User::find($id) : null;
    }
}
