<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * A közösségi média elérhetőségek (Facebook / Instagram / YouTube / TikTok) —
 * a superadmin a /admin/settings/social oldalon állítja be, a `site_settings`-ben
 * tárolva. A publikus lábléc + a Kapcsolat oldal Inertia shared propból olvassa
 * (csak a kitöltött linkek jelennek meg).
 */
class SocialLinks
{
    /** @var array<string, string> platform => emberi név (fix sorrend) */
    private const PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
    ];

    /** @var array<string, string> platform => rövidítés a lábléchez */
    private const ABBR = [
        'facebook' => 'FB',
        'instagram' => 'IG',
        'youtube' => 'YT',
        'tiktok' => 'TT',
    ];

    /**
     * Csak a kitöltött linkek, a publikus felülethez.
     *
     * @return list<array{platform: string, label: string, abbr: string, url: string, display: string}>
     */
    public function all(): array
    {
        $result = [];

        foreach (self::PLATFORMS as $platform => $label) {
            $url = $this->normalize((string) (SiteSetting::get("social_{$platform}") ?? ''));

            if ($url !== '') {
                $result[] = [
                    'platform' => $platform,
                    'label' => $label,
                    'abbr' => self::ABBR[$platform],
                    'url' => $url,
                    'display' => $this->display($url),
                ];
            }
        }

        return $result;
    }

    /**
     * A link rövid, olvasható alakja (séma + `www.` + záró `/` nélkül).
     */
    private function display(string $url): string
    {
        return rtrim(preg_replace('#^https?://(www\.)?#i', '', $url), '/');
    }

    /**
     * Minden platform (üres string is), az admin űrlaphoz.
     *
     * @return array<string, string>
     */
    public function forForm(): array
    {
        return collect(self::PLATFORMS)
            ->keys()
            ->mapWithKeys(fn (string $p) => [$p => (string) (SiteSetting::get("social_{$p}") ?? '')])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        foreach (array_keys(self::PLATFORMS) as $platform) {
            SiteSetting::set("social_{$platform}", $this->normalize((string) ($data[$platform] ?? '')));
        }
    }

    /**
     * @return list<string>
     */
    public function platforms(): array
    {
        return array_keys(self::PLATFORMS);
    }

    private function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }
}
