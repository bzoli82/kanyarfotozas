<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Services\CaptchaSettings;
use App\Services\InvoiceSettings;
use App\Services\MailSettings;
use App\Services\MonitoringSettings;
use App\Services\PaymentSettings;
use App\Services\SiteBranding;
use App\Services\SiteIdentity;
use App\Services\SystemReadiness;
use App\Services\WebScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Kritikus beállítások" (/admin/settings/critical) — csak superadmin. Egy helyre
 * gyűjti az oldal működéséhez elengedhetetlen konfigurációt (fizetés, tárhely,
 * e-mail, ütemező, feldolgozás) + a végleges oldal-név/domain beállítását.
 * A fizetési kulcsok itt szerkeszthetők (site_settings, titkosítva), a többihez
 * állapotjelzés + link a saját oldalára.
 */
class CriticalSettingsController extends Controller
{
    public function index(SystemReadiness $readiness, PaymentSettings $payments, SiteBranding $branding, InvoiceSettings $invoicing, MonitoringSettings $monitoring, BackupService $backup, MailSettings $mail, CaptchaSettings $captcha, WebScheduler $webScheduler): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Critical', [
            'groups' => $readiness->groups(),
            'payments' => $payments->settingsForForm(),
            'mail' => $mail->settingsForForm(),
            'captcha' => $captcha->settingsForForm(),
            'invoicing' => [
                ...$invoicing->toArray(),
                'vat_codes' => InvoiceSettings::VAT_CODES,
            ],
            'monitoring' => [
                ...$monitoring->toArray(),
                'backup_disk' => $backup->disk(),
                'backups' => $backup->list(10),
            ],
            'identity' => [
                'domain' => $branding->domain(),
                'slug' => $branding->slug(),
                'name' => $branding->name(),
                'current_domain' => app(SiteIdentity::class)->currentDomain(),
                'db_name' => app(SiteIdentity::class)->currentDbName(),
                'ipn_url' => url('/api/simplepay/ipn'),
                'stripe_webhook_url' => url('/api/stripe/webhook'),
                'barion_callback_url' => url('/api/barion/callback'),
            ],
            'deployReminders' => [
                // A böngésző→R2 közvetlen feltöltés CORS-origin-je — az „Oldal neve"
                // fülön történő domain-váltáskor automatikusan frissül.
                'cors_origin' => 'https://'.$branding->domain(),
            ],
            'webScheduler' => [
                'enabled' => $webScheduler->enabled(),
                'url' => $webScheduler->enabled() ? $webScheduler->url() : null,
            ],
        ]);
    }

    /**
     * A „webes ütemező" ki/be kapcsolása + (opcionálisan) új titkos token.
     */
    public function updateScheduler(Request $request, WebScheduler $webScheduler): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'regenerate' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['regenerate'])) {
            $webScheduler->regenerateToken();
        }

        $webScheduler->setEnabled($data['enabled']);

        return back()->with('success', $data['enabled']
            ? 'Webes ütemező bekapcsolva — másold a lenti URL-t a külső cron szolgáltatásba (1 perces intervallum).'
            : 'Webes ütemező kikapcsolva.');
    }

    /**
     * „Teszt most" — lefuttatja a soron következő ütemezett feladatokat, és
     * visszaadja a kimenetet (a zöld/piros jelzés visszaigazolására).
     */
    public function testScheduler(WebScheduler $webScheduler): RedirectResponse
    {
        try {
            $result = $webScheduler->runNow();

            return back()->with('success', 'Ütemező lefutott. Kimenet: '.$result['output']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Az ütemező-teszt hibára futott: '.$e->getMessage());
        }
    }

    public function updatePayments(Request $request, PaymentSettings $payments): RedirectResponse
    {
        $data = $request->validate([
            'default_provider' => ['required', Rule::in(['stripe', 'simplepay', 'barion'])],
            'stripe_enabled' => ['sometimes', 'boolean'],
            'stripe_publishable' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'simplepay_enabled' => ['sometimes', 'boolean'],
            'simplepay_merchant' => ['nullable', 'string', 'max:64'],
            'simplepay_secret_key' => ['nullable', 'string', 'max:255'],
            'simplepay_sandbox' => ['sometimes', 'boolean'],
            'barion_enabled' => ['sometimes', 'boolean'],
            'barion_payee' => ['nullable', 'string', 'max:255'],
            'barion_pos_key' => ['nullable', 'string', 'max:255'],
            'barion_sandbox' => ['sometimes', 'boolean'],
            'clear' => ['sometimes', 'array'],
            'clear.*' => [Rule::in(['stripe_secret', 'stripe_webhook_secret', 'simplepay_secret_key', 'barion_pos_key'])],
        ]);

        foreach ($data['clear'] ?? [] as $key) {
            $payments->clearSecret($key);
        }

        $payments->update($data);

        return back()->with('success', 'Fizetési beállítások elmentve.');
    }

    public function updateInvoicing(Request $request, InvoiceSettings $invoicing): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(InvoiceSettings::PROVIDERS)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'block_id' => ['nullable', 'integer', 'min:1'],
            'vat' => ['nullable', Rule::in(InvoiceSettings::VAT_CODES)],
            'auto' => ['sometimes', 'boolean'],
        ]);

        $invoicing->update($data);

        return back()->with('success', 'Számlázási beállítások elmentve.');
    }

    public function updateMail(Request $request, MailSettings $mail): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => ['nullable', 'string', 'max:255', 'required_if:mailer,smtp'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['sometimes', 'boolean'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        if (! empty($data['clear_password'])) {
            $mail->clearPassword();
        }

        $mail->update($data);

        return back()->with('success', 'E-mail beállítások elmentve.');
    }

    public function sendTestMail(Request $request, MailSettings $mail): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);

        $result = $mail->sendTest($data['to']);

        return $result['ok']
            ? back()->with('success', "Tesztlevél elküldve ide: {$data['to']}.")
            : back()->with('error', 'A tesztlevél nem ment el: '.$result['error']);
    }

    public function updateCaptcha(Request $request, CaptchaSettings $captcha): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'site_key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'clear_secret' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['clear_secret'])) {
            $captcha->clearSecret();
        }

        $captcha->update($data);

        return back()->with('success', 'hCaptcha beállítások elmentve.');
    }

    public function updateMonitoring(Request $request, MonitoringSettings $monitoring): RedirectResponse
    {
        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'clear_webhook' => ['sometimes', 'boolean'],
            'notify_email' => ['required', 'boolean'],
        ]);

        $monitoring->update($data);

        return back()->with('success', 'Monitoring beállítások elmentve.');
    }

    public function runBackup(BackupService $backup, MonitoringSettings $monitoring): RedirectResponse
    {
        try {
            $result = $backup->run();

            return back()->with('success', "Mentés kész: {$result['name']}.");
        } catch (\Throwable $e) {
            $monitoring->recordBackup(false, $e->getMessage());

            return back()->with('error', 'A mentés nem sikerült: '.$e->getMessage());
        }
    }

    public function downloadBackup(string $name, BackupService $backup): StreamedResponse
    {
        $key = $backup->download($name);
        abort_unless($key, 404);

        return Storage::disk($backup->disk())->download($key, $name);
    }

    public function updateIdentity(Request $request, SiteBranding $branding): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['nullable', 'string', 'max:120', 'regex:/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ]);

        $branding->setDomain($data['domain'] ?? '');

        return back()->with('success', $data['domain'] ? 'Domain elmentve.' : 'Domain törölve.');
    }

    /**
     * Előnézet: pontosan mit írna át a rebrand (e-mailek, beállítások) + a kézi lépések.
     */
    public function previewIdentity(Request $request, SiteIdentity $identity): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:120', 'regex:/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ]);

        return response()->json($identity->plan($data['domain']));
    }

    /**
     * Az átnevezés végrehajtása — a nem vizuális objektumokban (e-mailek, site_settings).
     */
    public function applyIdentity(Request $request, SiteIdentity $identity): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:120', 'regex:/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ]);

        try {
            $result = $identity->apply($data['domain']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        activity()->causedBy($request->user())
            ->log("Oldal átnevezve: {$result['from']} → {$result['to']} ({$result['emails_updated']} e-mail, {$result['settings_updated']} beállítás)");

        return back()->with('success',
            "Átnevezés kész: {$result['emails_updated']} e-mail + {$result['settings_updated']} beállítás átírva. "
            .'A hátralévő kézi lépéseket (DB-átnevezés, .env) az előnézet mutatja.');
    }
}
