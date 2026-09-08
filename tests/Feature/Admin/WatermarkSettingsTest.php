<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\WatermarkSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatermarkSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_watermark_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/admin/settings/watermark')->assertForbidden();
        $this->actingAs($photographer)->get('/admin/settings/watermark')->assertForbidden();
        $this->actingAs($superadmin)->get('/admin/settings/watermark')->assertOk();
    }

    public function test_superadmin_can_update_watermark_settings(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/watermark', [
            'text' => 'PROBAKANYAR.HU',
            'font' => 'dejavu_serif',
            'size' => 30,
            'density' => 5,
        ]);

        $response->assertRedirect();
        $this->assertSame('PROBAKANYAR.HU', SiteSetting::get('watermark_text'));
        $this->assertSame('dejavu_serif', SiteSetting::get('watermark_font'));
        $this->assertSame('30', SiteSetting::get('watermark_size'));
        $this->assertSame('5', SiteSetting::get('watermark_density'));
    }

    public function test_update_rejects_invalid_font_and_density(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/watermark', [
            'text' => 'X',
            'font' => 'comic-sans',
            'size' => 30,
            'density' => 99,
        ]);

        $response->assertSessionHasErrors(['font', 'density']);
    }

    public function test_preview_returns_a_webp_image_reflecting_unsaved_values(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->postJson('/admin/settings/watermark/preview', [
            'text' => 'ELONEZET TESZT',
            'font' => 'arimo',
            'size' => 24,
            'density' => 2,
        ]);

        $response->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(0, strlen($response->getContent()));

        // A mentes elott futott, tehat a tarolt beallitasoknak nem szabad megvaltozniuk.
        $this->assertNotSame('ELONEZET TESZT', SiteSetting::get('watermark_text'));
    }

    public function test_watermark_settings_service_falls_back_to_defaults_when_unset(): void
    {
        $watermark = app(WatermarkSettings::class);

        $this->assertSame(WatermarkSettings::DEFAULT_FONT, $watermark->fontKey());
        $this->assertSame(WatermarkSettings::DEFAULT_SIZE, $watermark->size());
        $this->assertSame(WatermarkSettings::DEFAULT_DENSITY, $watermark->density());
    }
}
