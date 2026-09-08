<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * A rendszamfelismero (OCR) rendszer superadmin altal kapcsolhato beallitasai
 * (/admin/settings/plate-recognition). A kepfeldolgozo pipeline (EPIC-13) ezt
 * kerdezi le: ha ki van kapcsolva, a felismeres/homalyositas lepes kimarad.
 *
 * A szolgaltato API kulcsa titkositva (Crypt) a `site_settings`-ben tarolodik —
 * ugyanaz a minta, mint a NAS-hitelesito adatoknal (App\Services\NasConnection).
 */
class PlateRecognitionSettings
{
    public const MODES = ['auto_blur', 'flag_only'];

    public const DEFAULT_MODE = 'auto_blur';

    public const DEFAULT_MIN_CONFIDENCE = 70;

    public const MIN_CONFIDENCE_FLOOR = 50;

    public const MIN_CONFIDENCE_CEILING = 99;

    /** Tamogatott OCR szolgaltatok. */
    public const PROVIDERS = ['platerecognizer', 'google_vision'];

    public const DEFAULT_PROVIDER = 'platerecognizer';

    public function enabled(): bool
    {
        return (bool) SiteSetting::get('plate_recognition_enabled', false);
    }

    public function mode(): string
    {
        $mode = (string) SiteSetting::get('plate_recognition_mode', self::DEFAULT_MODE);

        return in_array($mode, self::MODES, true) ? $mode : self::DEFAULT_MODE;
    }

    public function autoBlur(): bool
    {
        return $this->mode() === 'auto_blur';
    }

    public function minConfidence(): int
    {
        $value = (int) SiteSetting::get('plate_recognition_min_confidence', self::DEFAULT_MIN_CONFIDENCE);

        return max(self::MIN_CONFIDENCE_FLOOR, min(self::MIN_CONFIDENCE_CEILING, $value));
    }

    public function provider(): string
    {
        $provider = (string) SiteSetting::get('plate_recognition_provider', self::DEFAULT_PROVIDER);

        return in_array($provider, self::PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER;
    }

    public function apiKey(): ?string
    {
        $stored = SiteSetting::get('plate_recognition_api_key');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * Kesz-e a rendszer a tenyleges felismeresre (be van kapcsolva ES van API kulcs).
     */
    public function isConfigured(): bool
    {
        return $this->enabled() && $this->hasApiKey();
    }

    /**
     * @return array{enabled: bool, mode: string, min_confidence: int, provider: string, has_api_key: bool}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'mode' => $this->mode(),
            'min_confidence' => $this->minConfidence(),
            'provider' => $this->provider(),
            'has_api_key' => $this->hasApiKey(),
        ];
    }

    public function update(bool $enabled, string $mode, int $minConfidence, ?string $provider = null, ?string $apiKey = null): void
    {
        SiteSetting::set('plate_recognition_enabled', $enabled ? '1' : '');
        SiteSetting::set('plate_recognition_mode', in_array($mode, self::MODES, true) ? $mode : self::DEFAULT_MODE);
        SiteSetting::set('plate_recognition_min_confidence', (string) max(
            self::MIN_CONFIDENCE_FLOOR,
            min(self::MIN_CONFIDENCE_CEILING, $minConfidence)
        ));

        if ($provider !== null) {
            SiteSetting::set('plate_recognition_provider', in_array($provider, self::PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER);
        }

        // Ures API kulcs mezo = "hagyd valtozatlanul", nem torles.
        if ($apiKey !== null && $apiKey !== '') {
            SiteSetting::set('plate_recognition_api_key', Crypt::encryptString($apiKey));
        }
    }

    public function clearApiKey(): void
    {
        SiteSetting::set('plate_recognition_api_key', '');
    }
}
