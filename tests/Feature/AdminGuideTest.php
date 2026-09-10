<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminNavigation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_superadmin_sees_every_section_on_the_guide_page(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/guide')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Guide')
                ->has('sections')
                ->where('sections.0.items.0.href', '/admin/dashboard')
            );
    }

    public function test_photographer_only_sees_their_own_sections(): void
    {
        $response = $this->actingAs(User::factory()->photographer()->create())->get('/admin/guide');

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Guide')
            ->where('sections.0.items.0.href', '/photographer/dashboard')
        );
    }

    public function test_the_shared_nav_prop_is_present_for_team_members(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('adminNav', 10));
    }

    public function test_the_shared_nav_prop_is_null_for_guests(): void
    {
        $this->get('/events')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('adminNav', null));
    }

    public function test_admin_search_index_covers_nav_items_plus_deep_targets(): void
    {
        $index = AdminNavigation::searchIndex(User::factory()->superadmin()->create());

        $labels = collect($index)->pluck('label');
        // menüpont
        $this->assertTrue($labels->contains('Vízjel'));
        // „mélyen ülő" beállítás, amit nehéz megtalálni
        $this->assertTrue($labels->contains('Stripe kulcsok'));
        $this->assertTrue($labels->contains('Animációk (mozgás) beállítása'));

        $stripe = collect($index)->firstWhere('label', 'Stripe kulcsok');
        $this->assertSame('/admin/settings/critical', $stripe['href']);
        $this->assertStringContainsString('stripe', $stripe['keywords']);

        // fotós NEM lát superadmin-célokat
        $photographerIndex = AdminNavigation::searchIndex(User::factory()->photographer()->create());
        $this->assertFalse(collect($photographerIndex)->pluck('label')->contains('Stripe kulcsok'));
    }

    public function test_admin_search_prop_is_shared_to_team_members(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('adminSearch'));
    }
}
