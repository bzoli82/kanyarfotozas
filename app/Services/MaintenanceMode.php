<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Karbantartási mód: a publikus oldal mögé egy „hamarosan" lapot tesz, az admin
 * és a bejelentkezés elérhető marad. A `App\Http\Middleware\CheckMaintenanceMode`
 * végzi a tényleges blokkolást. (A `php artisan down` CLI-mód ettől független.)
 */
class MaintenanceMode
{
    public const DEFAULT_MESSAGE = 'Az oldal rövid karbantartás alatt — hamarosan visszatérünk.';

    public function enabled(): bool
    {
        return (bool) SiteSetting::get('maintenance_mode', false);
    }

    public function message(): string
    {
        return trim((string) SiteSetting::get('maintenance_message', '')) ?: self::DEFAULT_MESSAGE;
    }

    public function update(bool $enabled, ?string $message): void
    {
        SiteSetting::set('maintenance_mode', $enabled ? '1' : '0');
        SiteSetting::set('maintenance_message', trim((string) $message));
    }

    /**
     * @return array{enabled: bool, message: string, default_message: string}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'message' => trim((string) SiteSetting::get('maintenance_message', '')),
            'default_message' => self::DEFAULT_MESSAGE,
        ];
    }
}
