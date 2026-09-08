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
            'logo_part1' => 'KANYAR',
            'logo_part2' => 'FOTÓS',
            'nav_style' => 'transparent',
            'platform_name' => 'KanyarFotózás',
            'base_price_huf' => '1490',
            'watermark_text' => 'KANYARFOTÓZÁS',
            'watermark_font' => WatermarkSettings::DEFAULT_FONT,
            'watermark_size' => (string) WatermarkSettings::DEFAULT_SIZE,
            'watermark_density' => (string) WatermarkSettings::DEFAULT_DENSITY,
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
