<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * GEO = Generative Engine Optimization: hogy az AI-keresők (ChatGPT, Perplexity,
 * Google AI Overview, Gemini) megtalálják és HELYESEN idézzék az oldalt.
 *
 * A klasszikus SEO-réteg (szerver-oldali meta, JSON-LD, sitemap, gyors oldalak)
 * már ellátja a GEO igényeinek nagy részét. Ez a szolgáltatás EGY plusz dolgot ad:
 * egy `/llms.txt` fájlt — ez egy feltörekvő konvenció (mint a robots.txt), ami
 * emberi nyelven, tömören elmondja az AI-crawlereknek, MI ez az oldal és melyek
 * a fontos aloldalai.
 */
class GeoSettings
{
    public function enabled(): bool
    {
        return (bool) SiteSetting::get('llms_txt_enabled', true);
    }

    public function description(): string
    {
        $stored = SiteSetting::get('llms_txt_description');

        return is_string($stored) && trim($stored) !== '' ? $stored : $this->defaultDescription();
    }

    public function update(bool $enabled, string $description): void
    {
        SiteSetting::set('llms_txt_enabled', $enabled ? '1' : '0');
        SiteSetting::set('llms_txt_description', trim($description) ?: null);
    }

    public function defaultDescription(): string
    {
        $brand = app(SiteBranding::class)->name();

        return "A(z) {$brand} egy magyar motorsport fotó- és videó-értékesítő platform. "
            .'Fotósok pályanapokon, versenyeken és találkozókon fotózzák a résztvevőket; a látogatók '
            .'helyszín és időpont szerint megkeresik a magukról készült felvételeket, és regisztráció nélkül, '
            .'csak e-mail-címmel megvásárolják és vízjel nélkül letöltik. A fotósok az eladás előre '
            .'megbeszélt százalékát kapják; az esemény-szervezők bevétel-részesedésben részesülhetnek.';
    }
}
