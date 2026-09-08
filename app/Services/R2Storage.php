<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * A Cloudflare R2 (S3-kompatibilis) tároló kapcsolati adatai a `site_settings`
 * táblából — a superadmin az admin felületen (/admin/settings/storage) állítja,
 * nem kell a szerver `.env`-jét szerkeszteni. A titkos érték (secret access key)
 * titkosítva tárolódik; a `.env` / `config/filesystems.php` értékei szolgálnak
 * alapértelmezettként, amíg nincs semmi elmentve.
 *
 * Három disk osztja ugyanazt az account-endpointot + kulcspárt, csak a bucket tér el:
 *   r2_public  – kis publikus fájlok (thumbnail, vízjeles előnézet, HLS, hero)
 *   r2_private – feltöltött eredeti + megvásárolt letölthető változatok
 *   r2_import  – tömeges import „drop zone"
 */
class R2Storage
{
    private const DISKS = ['r2_public', 'r2_private', 'r2_import'];

    /**
     * Beállítja az r2_* filesystem diskek configját a jelenleg tárolt értékek szerint.
     * Az AppServiceProvider::boot()-ból fut (web + queue worker + cron egységesen).
     */
    public function applyRuntimeConfig(): void
    {
        $s = $this->rawSettings();

        $endpoint = $s['r2_endpoint'] ?: config('filesystems.disks.r2_public.endpoint');
        $key = $s['r2_access_key_id'] ?: config('filesystems.disks.r2_public.key');
        $secret = $this->decrypt($s['r2_secret_access_key']) ?: config('filesystems.disks.r2_public.secret');

        $buckets = [
            'r2_public' => $s['r2_public_bucket'] ?: config('filesystems.disks.r2_public.bucket'),
            'r2_private' => $s['r2_private_bucket'] ?: config('filesystems.disks.r2_private.bucket'),
            'r2_import' => $s['r2_import_bucket'] ?: config('filesystems.disks.r2_import.bucket'),
        ];

        foreach (self::DISKS as $disk) {
            config([
                "filesystems.disks.{$disk}.endpoint" => $endpoint,
                "filesystems.disks.{$disk}.key" => $key,
                "filesystems.disks.{$disk}.secret" => $secret,
                "filesystems.disks.{$disk}.bucket" => $buckets[$disk],
            ]);
        }

        if (filled($s['r2_public_url'] ?: config('filesystems.disks.r2_public.url'))) {
            config(['filesystems.disks.r2_public.url' => $s['r2_public_url'] ?: config('filesystems.disks.r2_public.url')]);
        }
    }

    public function isConfigured(): bool
    {
        $this->applyRuntimeConfig();

        $c = config('filesystems.disks.r2_private');

        return filled($c['endpoint']) && filled($c['key']) && filled($c['secret']) && filled($c['bucket']);
    }

    /** Non-titkos mezők + a titkos mező kitöltöttségének jelzője (az admin formhoz). */
    public function settingsForForm(): array
    {
        $s = $this->rawSettings();

        return [
            'endpoint' => $s['r2_endpoint'] ?: config('filesystems.disks.r2_public.endpoint'),
            'access_key_id' => $s['r2_access_key_id'] ?: config('filesystems.disks.r2_public.key'),
            'public_bucket' => $s['r2_public_bucket'] ?: config('filesystems.disks.r2_public.bucket'),
            'private_bucket' => $s['r2_private_bucket'] ?: config('filesystems.disks.r2_private.bucket'),
            'import_bucket' => $s['r2_import_bucket'] ?: config('filesystems.disks.r2_import.bucket'),
            'public_url' => $s['r2_public_url'] ?: config('filesystems.disks.r2_public.url'),
            'has_secret' => filled($s['r2_secret_access_key']) || filled(config('filesystems.disks.r2_private.secret')),
        ];
    }

    /**
     * Elmenti a kapcsolati adatokat. Az üres secret mezőt figyelmen kívül hagyja
     * (a már elmentett titkos érték megmarad).
     */
    public function update(array $data): void
    {
        SiteSetting::set('r2_endpoint', trim((string) ($data['endpoint'] ?? '')));
        SiteSetting::set('r2_access_key_id', trim((string) ($data['access_key_id'] ?? '')));
        SiteSetting::set('r2_public_bucket', trim((string) ($data['public_bucket'] ?? '')));
        SiteSetting::set('r2_private_bucket', trim((string) ($data['private_bucket'] ?? '')));
        SiteSetting::set('r2_import_bucket', trim((string) ($data['import_bucket'] ?? '')) ?: null);
        SiteSetting::set('r2_public_url', trim((string) ($data['public_url'] ?? '')));

        if (filled($data['secret_access_key'] ?? null)) {
            SiteSetting::set('r2_secret_access_key', Crypt::encryptString($data['secret_access_key']));
        }

        $this->applyRuntimeConfig();

        foreach (self::DISKS as $disk) {
            Storage::forgetDisk($disk);
        }
    }

    private function rawSettings(): array
    {
        $keys = [
            'r2_endpoint', 'r2_access_key_id', 'r2_secret_access_key',
            'r2_public_bucket', 'r2_private_bucket', 'r2_import_bucket', 'r2_public_url',
        ];

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
