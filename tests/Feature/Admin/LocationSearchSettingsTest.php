<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\GeoSearchSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSearchSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_gps_radius_search_is_disabled_by_default(): void
    {
        $this->assertFalse(app(GeoSearchSettings::class)->enabled());
    }

    public function test_only_superadmin_can_open_the_settings_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/location-search')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/location-search')->assertOk();
    }

    public function test_superadmin_can_toggle_the_feature(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/location-search', ['enabled' => true])->assertRedirect();
        $this->assertTrue(app(GeoSearchSettings::class)->enabled());

        $this->actingAs($superadmin)->put('/admin/settings/location-search', ['enabled' => false])->assertRedirect();
        $this->assertFalse(app(GeoSearchSettings::class)->enabled());
    }

    public function test_the_flag_is_shared_to_the_frontend(): void
    {
        $props = $this->get('/')->viewData('page')['props'];
        $this->assertFalse($props['geoSearch']);

        app(GeoSearchSettings::class)->update(true);

        $props = $this->get('/')->viewData('page')['props'];
        $this->assertTrue($props['geoSearch']);
    }
}
