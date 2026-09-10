<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MaintenanceMode;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function enable(string $message = 'Vissza 1 óra múlva.'): void
    {
        app(MaintenanceMode::class)->update(true, $message);
    }

    public function test_visitor_sees_the_maintenance_page(): void
    {
        $this->enable();

        $this->get('/')->assertStatus(503)->assertSee('Vissza 1 óra múlva.');
        $this->get('/events')->assertStatus(503);
        $this->get('/contact')->assertStatus(503);
    }

    public function test_admin_login_and_payment_webhook_stay_reachable(): void
    {
        $this->enable();

        $this->get('/login')->assertOk();
        // Fizetési webhook / IPN — folyamatban lévő rendelés nem akadhat el.
        $this->assertNotSame(503, $this->postJson('/api/stripe/webhook', [])->status());
    }

    public function test_logged_in_team_member_bypasses_maintenance(): void
    {
        $this->enable();

        $this->actingAs(User::factory()->photographer()->create())->get('/')->assertOk();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/dashboard')->assertOk();
    }

    public function test_superadmin_toggles_it_via_the_form(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->put('/admin/settings/maintenance', ['enabled' => true, 'message' => 'Fejlesztés.'])
            ->assertRedirect();

        $this->assertTrue(app(MaintenanceMode::class)->enabled());

        $this->actingAs($superadmin)
            ->put('/admin/settings/maintenance', ['enabled' => false, 'message' => '']);

        $this->assertFalse((bool) SiteSetting::get('maintenance_mode'));
        $this->get('/')->assertOk();
    }
}
