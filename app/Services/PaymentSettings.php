<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * A fizetési szolgáltatók (Stripe, SimplePay) hitelesítő adatai — a superadmin a
 * /admin/settings/critical oldalon állítja be őket, nem kell .env-et szerkeszteni
 * a szerveren. A titkos mezők Crypt-titkosítva a `site_settings`-ben tárolódnak
 * (ugyanaz a minta, mint App\Services\NasConnection / PlateRecognitionSettings).
 *
 * Ha az adatbázisban nincs érték, a config/.env szolgál alapértelmezettként —
 * így egy korábbi .env-alapú beállítás is tovább működik, amíg át nem állítják.
 */
class PaymentSettings
{
    private const SECRET_KEYS = ['stripe_secret', 'stripe_webhook_secret', 'simplepay_secret_key', 'barion_pos_key'];

    /**
     * A `site_settings` értékeket a config-ba tölti — hívd meg minden fizetési
     * művelet előtt (a payment.settings middleware ezt teszi a checkout/webhook route-okon).
     */
    public function applyRuntimeConfig(): void
    {
        $s = $this->raw();

        config([
            'payments.default' => $s['payment_default_provider'] ?: config('payments.default', 'stripe'),

            'services.stripe.key' => $s['stripe_publishable'] ?: config('services.stripe.key'),
            'services.stripe.secret' => $this->decrypt($s['stripe_secret']) ?: config('services.stripe.secret'),
            'services.stripe.webhook_secret' => $this->decrypt($s['stripe_webhook_secret']) ?: config('services.stripe.webhook_secret'),

            'services.simplepay.merchant' => $s['simplepay_merchant'] ?: config('services.simplepay.merchant'),
            'services.simplepay.secret_key' => $this->decrypt($s['simplepay_secret_key']) ?: config('services.simplepay.secret_key'),
            'services.simplepay.sandbox' => $this->sandbox($s),

            'services.barion.pos_key' => $this->decrypt($s['barion_pos_key']) ?: config('services.barion.pos_key'),
            'services.barion.payee' => $s['barion_payee'] ?: config('services.barion.payee'),
            'services.barion.sandbox' => $this->barionSandbox($s),
        ]);
    }

    public function stripeConfigured(): bool
    {
        $this->applyRuntimeConfig();

        return filled(config('services.stripe.secret'));
    }

    public function simplePayConfigured(): bool
    {
        $this->applyRuntimeConfig();

        return filled(config('services.simplepay.merchant')) && filled(config('services.simplepay.secret_key'));
    }

    public function barionConfigured(): bool
    {
        $this->applyRuntimeConfig();

        return filled(config('services.barion.pos_key')) && filled(config('services.barion.payee'));
    }

    /**
     * A superadmin ki tud kapcsolni egy szolgáltatót akkor is, ha a kulcsai be
     * vannak állítva (pl. csak SimplePay-t akar a pénztárban). Alapból minden be van.
     */
    public function providerEnabled(string $provider): bool
    {
        $value = SiteSetting::get("payment_enabled:{$provider}");

        return $value === null ? true : $value === '1';
    }

    public function stripeWebhookReady(): bool
    {
        $this->applyRuntimeConfig();

        return filled(config('services.stripe.webhook_secret'));
    }

    /**
     * Nem-titkos mezők + jelzők a titkos mezők kitöltöttségéről (az admin formhoz).
     *
     * @return array<string, mixed>
     */
    public function settingsForForm(): array
    {
        $this->applyRuntimeConfig();
        $s = $this->raw();

        return [
            'default_provider' => config('payments.default', 'stripe'),
            'stripe_enabled' => $this->providerEnabled('stripe'),
            'stripe_publishable' => config('services.stripe.key') ?? '',
            'stripe_has_secret' => filled(config('services.stripe.secret')),
            'stripe_has_webhook_secret' => filled(config('services.stripe.webhook_secret')),
            'simplepay_enabled' => $this->providerEnabled('simplepay'),
            'simplepay_merchant' => config('services.simplepay.merchant') ?? '',
            'simplepay_has_secret_key' => filled(config('services.simplepay.secret_key')),
            'simplepay_sandbox' => (bool) config('services.simplepay.sandbox'),
            'barion_enabled' => $this->providerEnabled('barion'),
            'barion_payee' => config('services.barion.payee') ?? '',
            'barion_has_pos_key' => filled(config('services.barion.pos_key')),
            'barion_sandbox' => (bool) config('services.barion.sandbox'),
            'env_fallback' => [
                'stripe' => blank($s['stripe_secret']) && filled(config('services.stripe.secret')),
                'simplepay' => blank($s['simplepay_secret_key']) && filled(config('services.simplepay.secret_key')),
                'barion' => blank($s['barion_pos_key']) && filled(config('services.barion.pos_key')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        SiteSetting::set('payment_default_provider', in_array($data['default_provider'] ?? null, ['stripe', 'simplepay', 'barion'], true)
            ? $data['default_provider']
            : 'stripe');

        SiteSetting::set('stripe_publishable', (string) ($data['stripe_publishable'] ?? ''));
        SiteSetting::set('simplepay_merchant', (string) ($data['simplepay_merchant'] ?? ''));
        SiteSetting::set('simplepay_sandbox', ! empty($data['simplepay_sandbox']) ? '1' : '');
        SiteSetting::set('barion_payee', (string) ($data['barion_payee'] ?? ''));
        SiteSetting::set('barion_sandbox', ! empty($data['barion_sandbox']) ? '1' : '');

        foreach (['stripe', 'simplepay', 'barion'] as $provider) {
            if (array_key_exists("{$provider}_enabled", $data)) {
                SiteSetting::set("payment_enabled:{$provider}", ! empty($data["{$provider}_enabled"]) ? '1' : '0');
            }
        }

        // Ures titkos mezo = "hagyd valtozatlanul".
        foreach (['stripe_secret', 'stripe_webhook_secret', 'simplepay_secret_key', 'barion_pos_key'] as $key) {
            if (filled($data[$key] ?? null)) {
                SiteSetting::set($key, Crypt::encryptString((string) $data[$key]));
            }
        }
    }

    public function clearSecret(string $key): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            SiteSetting::set($key, '');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function raw(): array
    {
        $keys = [
            'payment_default_provider', 'stripe_publishable', 'stripe_secret', 'stripe_webhook_secret',
            'simplepay_merchant', 'simplepay_secret_key', 'simplepay_sandbox',
            'barion_pos_key', 'barion_payee', 'barion_sandbox',
        ];

        return collect($keys)->mapWithKeys(fn (string $k) => [$k => SiteSetting::get($k)])->all();
    }

    /**
     * @param  array<string, string|null>  $s
     */
    private function sandbox(array $s): bool
    {
        // Ha van barmilyen mentett SimplePay beallitas, a mentett sandbox-jelzo szamit;
        // egyebkent a config/.env ertek (alapban true).
        if (filled($s['simplepay_merchant']) || filled($s['simplepay_secret_key'])) {
            return $s['simplepay_sandbox'] === '1';
        }

        return (bool) config('services.simplepay.sandbox', true);
    }

    /**
     * @param  array<string, string|null>  $s
     */
    private function barionSandbox(array $s): bool
    {
        if (filled($s['barion_pos_key']) || filled($s['barion_payee'])) {
            return $s['barion_sandbox'] === '1';
        }

        return (bool) config('services.barion.sandbox', true);
    }

    private function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
