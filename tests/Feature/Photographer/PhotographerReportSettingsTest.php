<?php

namespace Tests\Feature\Photographer;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerReportSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_photographer_dashboard_exposes_report_preferences(): void
    {
        $photographer = User::factory()->photographer()->create(['report_weekly' => true, 'report_monthly' => false]);

        $response = $this->actingAs($photographer)->get('/photographer/dashboard');

        $response->assertOk();
        $prefs = $response->viewData('page')['props']['reportPrefs'];
        $this->assertTrue($prefs['report_weekly']);
        $this->assertFalse($prefs['report_monthly']);
    }

    public function test_photographer_can_toggle_own_report_preferences(): void
    {
        $photographer = User::factory()->photographer()->create(['report_weekly' => false, 'report_monthly' => false]);

        $this->actingAs($photographer)
            ->put('/photographer/settings/reports', ['report_weekly' => true, 'report_monthly' => true])
            ->assertRedirect();

        $photographer->refresh();
        $this->assertTrue($photographer->report_weekly);
        $this->assertTrue($photographer->report_monthly);
    }

    public function test_admins_cannot_use_the_photographer_report_settings_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/photographer/settings/reports', ['report_weekly' => true, 'report_monthly' => true])
            ->assertForbidden();
    }
}
