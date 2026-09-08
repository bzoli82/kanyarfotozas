<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Hibakövetés + mentés beállításai (a superadmin a /admin/settings/critical
 * „Monitoring és mentés" szekciójában állítja). A webhook URL Crypt-titkosítva a
 * `site_settings`-ben (tartalmazhat tokent — pl. Slack/Discord incoming webhook).
 */
class MonitoringSettings
{
    public function webhookUrl(): ?string
    {
        $stored = SiteSetting::get('error_webhook_url');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasWebhook(): bool
    {
        return $this->webhookUrl() !== null;
    }

    /** Küldjön-e e-mailt a superadminoknak új hibánál (alap: igen). */
    public function notifyEmail(): bool
    {
        $value = SiteSetting::get('error_notify_email');

        return $value === null ? true : (bool) $value;
    }

    /**
     * @return array{at: ?Carbon, status: ?string, error: ?string, file: ?string}
     */
    public function lastBackup(): array
    {
        $at = SiteSetting::get('backup_last_run');

        return [
            'at' => $at ? Carbon::parse($at) : null,
            'status' => SiteSetting::get('backup_last_status') ?: null,
            'error' => SiteSetting::get('backup_last_error') ?: null,
            'file' => SiteSetting::get('backup_last_file') ?: null,
        ];
    }

    public function recordBackup(bool $ok, ?string $error = null, ?string $file = null): void
    {
        SiteSetting::set('backup_last_run', now()->toIso8601String());
        SiteSetting::set('backup_last_status', $ok ? 'ok' : 'failed');
        SiteSetting::set('backup_last_error', $ok ? '' : (string) $error);
        SiteSetting::set('backup_last_file', $ok ? (string) $file : (string) SiteSetting::get('backup_last_file'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $last = $this->lastBackup();

        return [
            'has_webhook' => $this->hasWebhook(),
            'notify_email' => $this->notifyEmail(),
            'backup' => [
                'at' => $last['at']?->toIso8601String(),
                'status' => $last['status'],
                'error' => $last['error'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        if (array_key_exists('notify_email', $data)) {
            SiteSetting::set('error_notify_email', empty($data['notify_email']) ? '0' : '1');
        }

        if (! empty($data['clear_webhook'])) {
            SiteSetting::set('error_webhook_url', '');
        } elseif (filled($data['webhook_url'] ?? null)) {
            SiteSetting::set('error_webhook_url', Crypt::encryptString(trim((string) $data['webhook_url'])));
        }
    }
}
