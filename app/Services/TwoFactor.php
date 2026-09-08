<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Kétfaktoros hitelesítés (TOTP, RFC 6238) az admin/superadmin fiókokhoz.
 * A titkos kulcs és a helyreállító kódok Crypt-titkosítva a `users` táblában.
 */
class TwoFactor
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** Az authenticator-appba beolvasható QR-kód SVG-ként. */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(
            app(SiteBranding::class)->name(),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(196, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($uri);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code), 1);
    }

    /**
     * A függőben lévő beállítás: titok + helyreállító kódok mentése (még nem megerősítve).
     */
    public function enable(User $user, string $secret): void
    {
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** A felhasználó beírt egy érvényes kódot → a 2FA élesítése. */
    public function confirm(User $user, string $code): bool
    {
        $secret = $this->secretFor($user);

        if ($secret === null || ! $this->verify($secret, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): void
    {
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->generateRecoveryCodes())),
        ])->save();
    }

    public function secretFor(User $user): ?string
    {
        if (blank($user->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(User $user): array
    {
        if (blank($user->two_factor_recovery_codes)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

            return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Egy helyreállító kód beváltása (törlődik a listából). */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = trim($code);
        $codes = $this->recoveryCodes($user);

        $match = collect($codes)->first(fn ($c) => hash_equals($c, $code));

        if ($match === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values(array_diff($codes, [$match])))),
        ])->save();

        return true;
    }

    /** Globális szabály: kötelező-e a 2FA minden admin/superadmin fióknál. */
    public function isRequiredForAdmins(): bool
    {
        return (bool) SiteSetting::get('two_factor_required', false);
    }

    public function setRequiredForAdmins(bool $required): void
    {
        SiteSetting::set('two_factor_required', $required ? '1' : '');
    }

    // ── Megbízható eszköz (30 nap) — állapotmentes, aláírt süti ──────────────

    public const TRUSTED_COOKIE = 'kf_2fa_device';

    public const TRUSTED_DAYS = 30;

    public function trustedDeviceToken(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'uid' => $user->id,
            'exp' => now()->addDays(self::TRUSTED_DAYS)->timestamp,
            'v' => $this->secretFingerprint($user),
        ]));
    }

    public function isTrustedDevice(User $user, ?string $cookieValue): bool
    {
        if (blank($cookieValue)) {
            return false;
        }

        try {
            $data = json_decode(Crypt::decryptString($cookieValue), true);
        } catch (\Throwable) {
            return false;
        }

        return is_array($data)
            && ($data['uid'] ?? null) === $user->id
            && (int) ($data['exp'] ?? 0) > now()->timestamp
            && hash_equals($this->secretFingerprint($user), (string) ($data['v'] ?? ''));
    }

    private function secretFingerprint(User $user): string
    {
        // A titok újragenerálása / 2FA kikapcsolása minden megbízható eszközt érvénytelenít.
        return substr(hash('sha256', (string) $user->two_factor_secret), 0, 16);
    }
}
