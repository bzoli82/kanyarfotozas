<?php

namespace Tests\Feature;

use App\Models\User;
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
}
