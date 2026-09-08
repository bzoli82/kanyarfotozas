<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Az e-mail küldés (SMTP) beállításait a `site_settings`-ből olvassa/írja — a
 * superadmin a /admin/settings/critical oldalon állítja be, nem kell a szerver
 * `.env`-jét szerkeszteni. A jelszó Crypt-titkosítva tárolódik (ugyanaz a minta,
 * mint NasConnection / PaymentSettings).
 *
 * Ha az adatbázisban nincs érték, a config/.env szolgál alapértelmezettként.
 * Az `applyRuntimeConfig()` az `AppServiceProvider::boot()`-ból fut, így a webre,
 * a queue workerre és a cron-parancsokra is egységesen érvényes.
 */
class MailSettings
{
    private const KEYS = [
        'mail_mailer', 'mail_host', 'mail_port', 'mail_encryption',
        'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name',
        'mail_reply_to',
    ];

    /**
     * A `site_settings` értékeket a `config('mail.*')`-ba tölti. Mielőtt a
     * MailManager először feloldja a mailert (a boot-ban hívva ez mindig előbb van).
     */
    public function applyRuntimeConfig(): void
    {
        $s = $this->raw();

        $mailer = $s['mail_mailer'] ?: config('mail.default', 'log');
        $encryption = $s['mail_encryption'] ?? '';

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $s['mail_host'] ?: config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => (int) ($s['mail_port'] ?: config('mail.mailers.smtp.port', 587)),
            'mail.mailers.smtp.username' => $s['mail_username'] ?: config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $this->decrypt($s['mail_password']) ?: config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.scheme' => match ($encryption) {
                'ssl' => 'smtps',
                'tls' => 'smtp',
                default => config('mail.mailers.smtp.scheme'),
            },
            'mail.from.address' => $s['mail_from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $s['mail_from_name'] ?: config('mail.from.name'),
        ]);
    }

    /**
     * A „Válasz" (Reply-To) cím, ha be van állítva — a vevő így valódi, figyelt
     * postafiókba tud válaszolni a rendszer-e-mailekre (a feladó `noreply@` marad).
     */
    public function replyTo(): ?Address
    {
        $address = (string) (SiteSetting::get('mail_reply_to') ?? '');

        if (blank($address)) {
            return null;
        }

        return new Address($address, (string) config('mail.from.name'));
    }

    public function isConfigured(): bool
    {
        $this->applyRuntimeConfig();

        return ! in_array((string) config('mail.default'), ['log', 'array'], true)
            && filled(config('mail.mailers.smtp.host'));
    }

    /**
     * Nem-titkos mezők + jelző a jelszó kitöltöttségéről (az admin formhoz).
     *
     * @return array<string, mixed>
     */
    public function settingsForForm(): array
    {
        $this->applyRuntimeConfig();
        $s = $this->raw();

        return [
            'mailer' => config('mail.default', 'log'),
            'host' => config('mail.mailers.smtp.host') ?? '',
            'port' => (string) config('mail.mailers.smtp.port', 587),
            'encryption' => $s['mail_encryption'] ?: 'tls',
            'username' => config('mail.mailers.smtp.username') ?? '',
            'has_password' => filled($s['mail_password']) || filled(config('mail.mailers.smtp.password')),
            'from_address' => config('mail.from.address') ?? '',
            'from_name' => config('mail.from.name') ?? '',
            'reply_to' => $s['mail_reply_to'] ?? '',
            'env_fallback' => blank($s['mail_host']) && filled(config('mail.mailers.smtp.host'))
                && config('mail.mailers.smtp.host') !== '127.0.0.1',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        SiteSetting::set('mail_mailer', in_array($data['mailer'] ?? null, ['smtp', 'log'], true) ? $data['mailer'] : 'log');
        SiteSetting::set('mail_host', (string) ($data['host'] ?? ''));
        SiteSetting::set('mail_port', (string) ($data['port'] ?? ''));
        SiteSetting::set('mail_encryption', in_array($data['encryption'] ?? null, ['tls', 'ssl', 'none'], true) ? $data['encryption'] : 'tls');
        SiteSetting::set('mail_username', (string) ($data['username'] ?? ''));
        SiteSetting::set('mail_from_address', (string) ($data['from_address'] ?? ''));
        SiteSetting::set('mail_from_name', (string) ($data['from_name'] ?? ''));
        SiteSetting::set('mail_reply_to', (string) ($data['reply_to'] ?? ''));

        // Üres jelszó mező = "hagyd változatlanul".
        if (filled($data['password'] ?? null)) {
            SiteSetting::set('mail_password', Crypt::encryptString((string) $data['password']));
        }

        $this->applyRuntimeConfig();
        rescue(fn () => app('mail.manager')->forgetMailers(), report: false);
    }

    public function clearPassword(): void
    {
        SiteSetting::set('mail_password', '');
    }

    /**
     * Tesztlevél az adott címre a JELENLEGI beállításokkal.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function sendTest(string $to): array
    {
        $this->applyRuntimeConfig();
        rescue(fn () => app('mail.manager')->forgetMailers(), report: false);

        try {
            $brand = app(SiteBranding::class)->name();

            Mail::raw(
                "Ez egy teszt e-mail a(z) {$brand} adminfelületéről.\n\n"
                .'Ha megkaptad, az e-mail küldés helyesen van beállítva.',
                fn ($message) => $message->to($to)->subject("{$brand} — teszt e-mail"),
            );

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function raw(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $k) => [$k => SiteSetting::get($k)])->all();
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
