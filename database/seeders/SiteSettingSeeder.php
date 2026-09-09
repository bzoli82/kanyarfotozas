<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Services\WatermarkSettings;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'theme_mode' => 'dark',
            'accent_color' => '#e63946',
            'border_radius' => '12',
            'font_family' => 'Inter',
            'logo_part1' => 'ROADSIDE',
            'logo_part2' => 'PHOTO',
            'nav_style' => 'transparent',
            'platform_name' => 'RoadsidePhoto',
            'site_domain' => 'roadsidephoto.eu',
            'mail_from_address' => 'noreply@roadsidephoto.eu',
            'base_price_huf' => '1490',
            'watermark_text' => 'ROADSIDEPHOTO',
            'watermark_font' => WatermarkSettings::DEFAULT_FONT,
            'watermark_size' => (string) WatermarkSettings::DEFAULT_SIZE,
            'watermark_density' => (string) WatermarkSettings::DEFAULT_DENSITY,
        ];

        // SiteSetting::set() a `Cache::forget`-et is elvégzi — enélkül egy korábban
        // (a seeder előtt) beolvasott üres default 300 mp-ig eltakarná az új értéket
        // (pl. a DemoDataSeeder `mailDomain()`-je ilyenkor `example.test`-et adna).
        foreach ($defaults as $key => $value) {
            SiteSetting::set($key, $value);
        }
    }
}
