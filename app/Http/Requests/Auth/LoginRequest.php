<?php

namespace App\Http\Requests\Auth;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bejelentkezés + többrétegű brute-force védelem:
 *
 *  1. **E-mail + IP** páronként {@see self::MAX_PER_IDENTITY} sikertelen próba után
 *     ideiglenes időzár (cache-alapú RateLimiter, 15 perces ablak).
 *  2. **IP-nként** összesen {@see self::MAX_PER_IP} sikertelen próba / ablak — az
 *     e-mail-cím forgatásával próbálkozó támadó ellen.
 *  3. **Fiók-szintű zár**: egy e-mail-cím {@see self::ACCOUNT_LOCK_THRESHOLD}
 *     sikertelen próba után BÁRMELY IP-ről zárolódik (a `failed_login_attempts`
 *     naplóból számolva — elosztott támadás ellen). Sikeres belépés törli.
 *
 * Sikertelen próbánál egységes „Hibás e-mail cím vagy jelszó." üzenet megy vissza
 * (nem árulja el, létezik-e a fiók).
 */
class LoginRequest extends FormRequest
{
    public const MAX_PER_IDENTITY = 5;

    public const MAX_PER_IP = 25;

    public const ACCOUNT_LOCK_THRESHOLD = 10;

    public const ACCOUNT_LOCK_MINUTES = 15;

    private const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->ensureAccountIsNotLockedOut();

        $user = User::query()->where('email', $this->identity())->first();

        if (! $user || ! Hash::check((string) $this->input('password'), (string) $user->password)) {
            event(new Failed('web', $user, ['email' => $this->identity()]));
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            RateLimiter::hit($this->ipKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Hibás e-mail cím vagy jelszó.',
            ]);
        }

        if (! $user->is_active) {
            event(new Failed('web', $user, ['email' => $this->identity()]));
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'A fiók inaktív — kérj hozzáférést az üzemeltetőtől.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipKey());
        FailedLoginAttempt::query()->where('email', $this->identity())->delete();

        $twoFactor = app(TwoFactor::class);

        if ($user->hasTwoFactorEnabled() && ! $twoFactor->isTrustedDevice($user, $this->cookie(TwoFactor::TRUSTED_COOKIE))) {
            $this->session()->put('login.2fa', [
                'id' => $user->id,
                'remember' => $this->boolean('remember'),
                'at' => now()->timestamp,
            ]);

            return; // a controller a 2FA-kihívás oldalra irányít
        }

        Auth::login($user, $this->boolean('remember'));
    }

    public function needsTwoFactorChallenge(): bool
    {
        return $this->session()->has('login.2fa');
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        $identityLocked = RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PER_IDENTITY);
        $ipLocked = RateLimiter::tooManyAttempts($this->ipKey(), self::MAX_PER_IP);

        if (! $identityLocked && ! $ipLocked) {
            return;
        }

        event(new Lockout($this));

        $seconds = (int) max(
            $identityLocked ? RateLimiter::availableIn($this->throttleKey()) : 0,
            $ipLocked ? RateLimiter::availableIn($this->ipKey()) : 0,
        );

        throw ValidationException::withMessages([
            'email' => 'Túl sok sikertelen próbálkozás. Próbáld újra '.$this->humanWait($seconds).' múlva.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureAccountIsNotLockedOut(): void
    {
        $email = $this->identity();

        if ($email === '') {
            return;
        }

        $recentFailures = FailedLoginAttempt::query()
            ->where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::ACCOUNT_LOCK_MINUTES))
            ->count();

        if ($recentFailures < self::ACCOUNT_LOCK_THRESHOLD) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Ezt a fiókot a sok sikertelen próbálkozás miatt ideiglenesen zároltuk. Próbáld újra kb. '
                .self::ACCOUNT_LOCK_MINUTES.' perc múlva.',
        ]);
    }

    private function identity(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }

    public function throttleKey(): string
    {
        return 'login:'.Str::transliterate($this->identity().'|'.$this->ip());
    }

    private function ipKey(): string
    {
        return 'login-ip:'.$this->ip();
    }

    private function humanWait(int $seconds): string
    {
        return $seconds >= 60
            ? (int) ceil($seconds / 60).' perc'
            : max(1, $seconds).' másodperc';
    }
}
