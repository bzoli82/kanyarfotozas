<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * hCaptcha a publikus űrlapokhoz (Kapcsolat, „Kérdés a fotóshoz") — a superadmin
 * a /admin/settings/critical oldalon kapcsolja be és adja meg a kulcsokat.
 * A `site_settings`-ben tárolva (a secret **Crypt-titkosítva**), `.env`/config
 * fallbackkel — ugyanaz a minta, mint PaymentSettings / MailSettings.
 *
 * Ha be van kapcsolva, a Kapcsolat űrlapon a hCaptcha widget VÁLTJA a FormGuard
 * számtani kérdését; a többi FormGuard-réteg (time-trap, proof-of-work, honeypot,
 * replay, rate limit) változatlanul él.
 */
class CaptchaSettings
{
    private const VERIFY_URL = 'https://api.hcaptcha.com/siteverify';

    public function enabled(): bool
    {
        if (! $this->toggle()) {
            return false;
        }

        return filled($this->siteKey()) && filled($this->secret());
    }

    public function siteKey(): string
    {
        return (string) (SiteSetting::get('hcaptcha_site_key') ?: config('services.hcaptcha.site_key'));
    }

    /**
     * A nézetnek átadott állapot: `{ enabled, site_key }`.
     *
     * @return array{enabled: bool, site_key: string}
     */
    public function forView(): array
    {
        return [
            'enabled' => $this->enabled(),
            'site_key' => $this->enabled() ? $this->siteKey() : '',
        ];
    }

    /**
     * A hCaptcha token ellenőrzése a hCaptcha szerverén.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        return (bool) rescue(function () use ($token, $ip) {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, array_filter([
                'secret' => $this->secret(),
                'response' => $token,
                'remoteip' => $ip,
                'sitekey' => $this->siteKey(),
            ]));

            return $response->successful() && $response->json('success') === true;
        }, false, false);
    }

    /**
     * @return array{enabled: bool, site_key: string, has_secret: bool, env_fallback: bool}
     */
    public function settingsForForm(): array
    {
        return [
            'enabled' => $this->toggle(),
            'site_key' => (string) (SiteSetting::get('hcaptcha_site_key') ?? config('services.hcaptcha.site_key') ?? ''),
            'has_secret' => filled(SiteSetting::get('hcaptcha_secret')) || filled(config('services.hcaptcha.secret')),
            'env_fallback' => blank(SiteSetting::get('hcaptcha_secret')) && filled(config('services.hcaptcha.secret')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        SiteSetting::set('hcaptcha_enabled', ! empty($data['enabled']) ? '1' : '0');
        SiteSetting::set('hcaptcha_site_key', (string) ($data['site_key'] ?? ''));

        if (filled($data['secret'] ?? null)) {
            SiteSetting::set('hcaptcha_secret', Crypt::encryptString((string) $data['secret']));
        }
    }

    public function clearSecret(): void
    {
        SiteSetting::set('hcaptcha_secret', '');
    }

    private function toggle(): bool
    {
        $value = SiteSetting::get('hcaptcha_enabled');

        return $value === null
            ? (bool) config('services.hcaptcha.enabled', false)
            : $value === '1';
    }

    private function secret(): string
    {
        $stored = SiteSetting::get('hcaptcha_secret');

        if (filled($stored)) {
            return (string) rescue(fn () => Crypt::decryptString($stored), '', false);
        }

        return (string) config('services.hcaptcha.secret');
    }
}
