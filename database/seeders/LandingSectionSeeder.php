<?php

namespace Database\Seeders;

use App\Models\LandingSection;
use Illuminate\Database\Seeder;

class LandingSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['key' => 'hero', 'label' => 'Hero', 'is_locked' => true, 'is_visible' => true],
            ['key' => 'how_it_works', 'label' => 'Hogyan működik', 'is_locked' => false, 'is_visible' => true],
            ['key' => 'latest_events', 'label' => 'Legfrissebb események', 'is_locked' => true, 'is_visible' => true],
            ['key' => 'video_showcase', 'label' => 'Video showcase', 'is_locked' => false, 'is_visible' => false],
            ['key' => 'stats', 'label' => 'Statisztikák', 'is_locked' => false, 'is_visible' => true],
            ['key' => 'payment_methods', 'label' => 'Fizetési módok', 'is_locked' => false, 'is_visible' => true],
            ['key' => 'photographers', 'label' => 'Fotósoknak', 'is_locked' => false, 'is_visible' => false],
            ['key' => 'map', 'label' => 'Helyszíntérkép', 'is_locked' => false, 'is_visible' => false],
            ['key' => 'reviews', 'label' => 'Vásárlói vélemények', 'is_locked' => false, 'is_visible' => false],
            ['key' => 'faq_mini', 'label' => 'GYIK mini', 'is_locked' => false, 'is_visible' => true],
            ['key' => 'social', 'label' => 'Közösségi média', 'is_locked' => false, 'is_visible' => false],
            ['key' => 'footer', 'label' => 'Footer', 'is_locked' => true, 'is_visible' => true],
        ];

        foreach ($sections as $index => $section) {
            LandingSection::query()->updateOrCreate(['key' => $section['key']], [
                ...$section,
                'sort_order' => $index + 1,
                'config' => [],
            ]);
        }
    }
}
