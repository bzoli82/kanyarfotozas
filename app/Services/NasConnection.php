<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * A NAS (SFTP) kapcsolati adatait a `site_settings` tablabol olvassa/irja —
 * a superadmin az admin feluleten (/admin/settings/storage) allithatja be
 * oket, nem kell .env-et szerkeszteni. A titkos ertekek (jelszo, privat kulcs)
 * titkositva taroldnak.
 *
 * Ha meg nincs semmi elmentve az adatbazisban, a .env / config/filesystems.php
 * ertekei szolgalnak alapertelmezettkent (igy egy korabbi .env-alapu beallitas
 * is tovabb mukodik, amig at nem allitjak az admin feluleten).
 */
class NasConnection
{
    private const SECRET_KEYS = ['nas_password', 'nas_private_key', 'nas_private_key_passphrase'];

    /**
     * Beallitja a 'nas' filesystem disk configjat a jelenleg tarolt ertekek szerint.
     * Hivd meg brmely Storage::disk('nas') hasznalat elott (job, controller).
     */
    public function applyRuntimeConfig(): void
    {
        $settings = $this->rawSettings();

        config([
            'filesystems.disks.nas.host' => $settings['nas_host'] ?: config('filesystems.disks.nas.host'),
            'filesystems.disks.nas.port' => (int) ($settings['nas_port'] ?: config('filesystems.disks.nas.port', 22)),
            'filesystems.disks.nas.username' => $settings['nas_username'] ?: config('filesystems.disks.nas.username'),
            'filesystems.disks.nas.password' => $this->decrypt($settings['nas_password']) ?: config('filesystems.disks.nas.password'),
            'filesystems.disks.nas.privateKey' => $this->decrypt($settings['nas_private_key']) ?: config('filesystems.disks.nas.privateKey'),
            'filesystems.disks.nas.passphrase' => $this->decrypt($settings['nas_private_key_passphrase']) ?: config('filesystems.disks.nas.passphrase'),
            'filesystems.disks.nas.root' => $settings['nas_root'] ?: config('filesystems.disks.nas.root', '/kanyarfotozas'),
        ]);
    }

    public function isConfigured(): bool
    {
        $this->applyRuntimeConfig();

        $c = config('filesystems.disks.nas');

        return filled($c['host']) && filled($c['username']) && (filled($c['password']) || filled($c['privateKey']));
    }

    /**
     * Nem-titkos mezok + jelzok a titkos mezok kitoltottsegerol (az admin formhoz).
     */
    public function settingsForForm(): array
    {
        $settings = $this->rawSettings();

        return [
            'host' => $settings['nas_host'] ?? config('filesystems.disks.nas.host'),
            'port' => $settings['nas_port'] ?? (string) config('filesystems.disks.nas.port', 22),
            'username' => $settings['nas_username'] ?? config('filesystems.disks.nas.username'),
            'root' => $settings['nas_root'] ?? config('filesystems.disks.nas.root', '/kanyarfotozas'),
            'has_password' => filled($settings['nas_password']) || filled(config('filesystems.disks.nas.password')),
            'has_private_key' => filled($settings['nas_private_key']) || filled(config('filesystems.disks.nas.privateKey')),
        ];
    }

    /**
     * Elmenti a kapcsolati adatokat. Az ures jelszo/privat kulcs mezot figyelmen
     * kivul hagyja (a mar elmentett titkos ertek megmarad).
     */
    public function updateSettings(array $data): void
    {
        SiteSetting::set('nas_host', $data['host']);
        SiteSetting::set('nas_port', (string) $data['port']);
        SiteSetting::set('nas_username', $data['username']);
        SiteSetting::set('nas_root', $data['root']);

        if (filled($data['password'] ?? null)) {
            SiteSetting::set('nas_password', Crypt::encryptString($data['password']));
            SiteSetting::set('nas_private_key', '');
        }

        if (filled($data['private_key'] ?? null)) {
            SiteSetting::set('nas_private_key', Crypt::encryptString($data['private_key']));
            SiteSetting::set('nas_password', '');
        }

        if (array_key_exists('private_key_passphrase', $data) && filled($data['private_key_passphrase'])) {
            SiteSetting::set('nas_private_key_passphrase', Crypt::encryptString($data['private_key_passphrase']));
        }
    }

    private function rawSettings(): array
    {
        $keys = ['nas_host', 'nas_port', 'nas_username', 'nas_root', 'nas_password', 'nas_private_key', 'nas_private_key_passphrase'];

        return collect($keys)->mapWithKeys(fn (string $key) => [$key => SiteSetting::get($key)])->all();
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
