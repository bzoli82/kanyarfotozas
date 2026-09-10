<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\OnboardingChecklist;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_items_reflect_real_state(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $checklist = app(OnboardingChecklist::class);
        $byKey = collect($checklist->items($superadmin))->keyBy('key');

        $this->assertFalse($byKey['event']['done']);
        $this->assertFalse($byKey['hero']['done']);
        $this->assertFalse($byKey['photographer']['done']);

        Event::factory()->create();
        HeroSlide::factory()->create();
        User::factory()->photographer()->create();

        $byKey = collect(app(OnboardingChecklist::class)->items($superadmin))->keyBy('key');
        $this->assertTrue($byKey['event']['done']);
        $this->assertTrue($byKey['hero']['done']);
        $this->assertTrue($byKey['photographer']['done']);
    }

    public function test_dashboard_hides_the_card_when_dismissed(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $props = $this->actingAs($superadmin)->get('/admin/dashboard')->viewData('page')['props'];
        $this->assertNotNull($props['onboarding']);

        $this->actingAs($superadmin)->post('/admin/dashboard/onboarding/dismiss')->assertRedirect();

        $props = $this->actingAs($superadmin)->get('/admin/dashboard')->viewData('page')['props'];
        $this->assertNull($props['onboarding']);
        $this->assertTrue((bool) SiteSetting::get('onboarding_dismissed'));
    }

    public function test_legal_pages_count_as_done_only_when_filled(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $done = fn () => collect(app(OnboardingChecklist::class)->items($superadmin))->keyBy('key')['legal']['done'];

        $this->assertFalse($done());

        SiteSetting::set('legal_impressum', '# Impresszum Kft.');
        SiteSetting::set('legal_terms', '# ÁSZF');

        $this->assertTrue($done());
    }
}
