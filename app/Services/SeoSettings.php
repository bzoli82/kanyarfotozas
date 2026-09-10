<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * A publikus oldalak SEO-beállításai — a superadmin a /admin/settings/seo
 * oldalon szerkeszti. Az `App\Services\Seo` (a tényleges meta-generátor) innen
 * olvassa az alapértelmezett leírást, a cím-kiegészítést, az OG-képet és a
 * "kereshetőség" globális kapcsolót.
 */
class SeoSettings
{
    public const DEFAULT_TITLE_SUFFIX = 'motorsport fotózás';

    public const DEFAULT_DESCRIPTION = 'Profi fotók és videók autós és motoros pályanapokról, versenyekről. Keresd meg a saját köreidet helyszín és időpont szerint, és töltsd le vízjel nélkül.';

    public function description(): string
    {
        return trim((string) SiteSetting::get('seo_description', '')) ?: self::DEFAULT_DESCRIPTION;
    }

    public function titleSuffix(): string
    {
        return trim((string) SiteSetting::get('seo_title_suffix', '')) ?: self::DEFAULT_TITLE_SUFFIX;
    }

    /** A `public` diskre feltöltött egyedi OG-kép relatív kulcsa (vagy null). */
    public function ogImagePath(): ?string
    {
        $path = trim((string) SiteSetting::get('seo_og_image_path', ''));

        return $path !== '' ? $path : null;
    }

    public function ogImageUrl(): string
    {
        // 1) kézzel feltöltött OG-kép → 2) a logóból automatikusan generált →
        // 3) a beépített minta.
        $path = $this->ogImagePath() ?? app(SiteBranding::class)->ogAutoPath();

        if ($path === null) {
            return url('/images/watermark-preview-sample.jpg');
        }

        $base = MediaStorage::publicBaseUrl();

        return Str::startsWith($base, ['http://', 'https://'])
            ? $base.'/'.ltrim($path, '/')
            : url($base.'/'.ltrim($path, '/'));
    }

    /**
     * Globális kapcsoló: engedjük-e a keresőket indexelni. KI: minden oldal
     * `noindex`, a robots.txt mindent tilt (indulás előtti / karbantartási mód).
     */
    public function searchVisible(): bool
    {
        $value = SiteSetting::get('seo_search_visible');

        return $value === null ? true : (bool) $value;
    }

    public function googleVerification(): ?string
    {
        return trim((string) SiteSetting::get('seo_google_verification', '')) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description(),
            'title_suffix' => $this->titleSuffix(),
            'og_image_url' => $this->ogImageUrl(),
            'og_image_is_custom' => $this->ogImagePath() !== null,
            'search_visible' => $this->searchVisible(),
            'google_verification' => $this->googleVerification(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        if (array_key_exists('description', $data)) {
            SiteSetting::set('seo_description', trim((string) ($data['description'] ?? '')));
        }

        if (array_key_exists('title_suffix', $data)) {
            SiteSetting::set('seo_title_suffix', trim((string) ($data['title_suffix'] ?? '')));
        }

        if (array_key_exists('search_visible', $data)) {
            SiteSetting::set('seo_search_visible', empty($data['search_visible']) ? '0' : '1');
        }

        if (array_key_exists('google_verification', $data)) {
            SiteSetting::set('seo_google_verification', trim((string) ($data['google_verification'] ?? '')));
        }
    }

    public function setOgImagePath(?string $path): void
    {
        SiteSetting::set('seo_og_image_path', $path ?? '');
    }
}
