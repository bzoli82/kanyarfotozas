<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'HU', 'name_hu' => 'Magyarország', 'name_en' => 'Hungary', 'flag_emoji' => '🇭🇺'],
            ['code' => 'DE', 'name_hu' => 'Németország', 'name_en' => 'Germany', 'flag_emoji' => '🇩🇪'],
            ['code' => 'AT', 'name_hu' => 'Ausztria', 'name_en' => 'Austria', 'flag_emoji' => '🇦🇹'],
            ['code' => 'SK', 'name_hu' => 'Szlovákia', 'name_en' => 'Slovakia', 'flag_emoji' => '🇸🇰'],
            ['code' => 'HR', 'name_hu' => 'Horvátország', 'name_en' => 'Croatia', 'flag_emoji' => '🇭🇷'],
            ['code' => 'RO', 'name_hu' => 'Románia', 'name_en' => 'Romania', 'flag_emoji' => '🇷🇴'],
            ['code' => 'SI', 'name_hu' => 'Szlovénia', 'name_en' => 'Slovenia', 'flag_emoji' => '🇸🇮'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(['code' => $country['code']], [
                ...$country,
                'active' => true,
            ]);
        }
    }
}
