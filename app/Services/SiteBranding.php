<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Az oldal neve / márkajele — superadmin szerkeszti a /admin/settings/branding
 * oldalon. Egyetlen forrás: a fejléc logó, az oldalcímek, az e-mailek és a
 * vízjel is innen veszi a nevet (a végleges név még nincs eldöntve).
 *
 * A kétszínű logó két részből áll: `logoLead` (alap szín) + `logoTail` (akcent).
 */
class SiteBranding
{
    public const DEFAULT_NAME = 'KanyarFotó';

    public const DEFAULT_LOGO_LEAD = 'KANYAR';

    public const DEFAULT_LOGO_TAIL = 'FOTÓS';

    public function name(): string
    {
        $name = trim((string) SiteSetting::get('site_name', ''));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    public function logoLead(): string
    {
        $value = trim((string) SiteSetting::get('site_logo_lead', ''));

        return $value !== '' ? $value : self::DEFAULT_LOGO_LEAD;
    }

    public function logoTail(): string
    {
        return trim((string) SiteSetting::get('site_logo_tail', self::DEFAULT_LOGO_TAIL));
    }

    /**
     * Az oldal VÉGLEGES domainje (pl. `kanyarfotozas.hu`) — a rendszer-e-mailek
     * (superadmin / demo fiókok), a megosztási URL-ek és a DB-név ebből képződik.
     * Amíg üres, a `.env` `APP_URL` hosztneve az alapértelmezett.
     */
    public function domain(): string
    {
        $stored = trim((string) SiteSetting::get('site_domain', ''));

        if ($stored !== '') {
            return $stored;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    /**
     * A domain gépnév-barát alakja (TLD nélkül, csak [a-z0-9]) — a javasolt
     * adatbázisnév és a rendszer-azonosítók alapja. Pl. `kanyarfotozas.hu` → `kanyarfotozas`.
     */
    public function slug(): string
    {
        $host = preg_replace('/^www\./', '', $this->domain());
        $firstLabel = explode('.', $host)[0] ?? $host;

        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($firstLabel)) ?: 'app';
    }

    /**
     * Érvényes e-mail domain a rendszer-fiókokhoz. Amíg nincs végleges domain
     * beállítva (a fejlesztői `localhost` nem valós), egy semleges placeholder.
     */
    public function mailDomain(): string
    {
        $domain = $this->domain();

        if (str_contains($domain, '.') && ! str_contains($domain, 'localhost') && ! str_contains($domain, '127.0.0.1')) {
            return $domain;
        }

        return 'example.test';
    }

    /**
     * Rendszer-e-mail cím a domainen (pl. `noreply@kanyarfotozas.hu`).
     */
    public function systemEmail(string $localPart = 'noreply'): string
    {
        return $localPart.'@'.$this->mailDomain();
    }

    public function setDomain(string $domain): void
    {
        SiteSetting::set('site_domain', mb_strtolower(trim($domain)));
    }

    /** A vízjel alapból a márkanév nagybetűs alakja (a branding felülírja). */
    public function watermarkText(): string
    {
        return mb_strtoupper($this->name());
    }

    /**
     * @return array{name: string, logo_lead: string, logo_tail: string, domain: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'logo_lead' => $this->logoLead(),
            'logo_tail' => $this->logoTail(),
            'domain' => $this->domain(),
        ];
    }

    public function update(string $name, string $logoLead, string $logoTail): void
    {
        SiteSetting::set('site_name', trim($name));
        SiteSetting::set('site_logo_lead', trim($logoLead));
        SiteSetting::set('site_logo_tail', trim($logoTail));

        // "Az ide beírt érték a vízjelet is átírja."
        SiteSetting::set('watermark_text', mb_strtoupper(trim($name)));
    }
}
