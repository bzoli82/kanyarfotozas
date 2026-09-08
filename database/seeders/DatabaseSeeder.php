<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            RolePermissionSeeder::class,
            LandingSectionSeeder::class,
            SiteSettingSeeder::class,
            FaqItemSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
