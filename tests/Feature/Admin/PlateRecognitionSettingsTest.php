<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PlateRecognitionSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlateRecognitionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_plate_recognition_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/plate-recognition')->assertForbidden();
        $this->actingAs(User::factory()->photographer()->create())->get('/admin/settings/plate-recognition')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/plate-recognition')->assertOk();
    }

    public function test_defaults_to_disabled(): void
    {
        $settings = app(PlateRecognitionSettings::class);

        $this->assertFalse($settings->enabled());
        $this->assertSame(PlateRecognitionSettings::DEFAULT_MODE, $settings->mode());
        $this->assertSame(PlateRecognitionSettings::DEFAULT_MIN_CONFIDENCE, $settings->minConfidence());
    }

    public function test_superadmin_can_toggle_and_configure(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => true,
            'mode' => 'flag_only',
            'min_confidence' => 85,
        ]);

        $response->assertRedirect();

        $settings = app(PlateRecognitionSettings::class);
        $this->assertTrue($settings->enabled());
        $this->assertSame('flag_only', $settings->mode());
        $this->assertFalse($settings->autoBlur());
        $this->assertSame(85, $settings->minConfidence());
    }

    public function test_api_key_is_stored_encrypted_and_never_exposed(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => true,
            'mode' => 'auto_blur',
            'min_confidence' => 70,
            'provider' => 'platerecognizer',
            'api_key' => 'super-secret-token-123',
        ])->assertRedirect();

        $settings = app(PlateRecognitionSettings::class);
        $this->assertSame('super-secret-token-123', $settings->apiKey());
        $this->assertTrue($settings->hasApiKey());
        $this->assertTrue($settings->isConfigured());

        // A nyers kulcs sose kerul a DB-be es sose megy ki propkent.
        $raw = SiteSetting::query()->find('plate_recognition_api_key')->value;
        $this->assertStringNotContainsString('super-secret-token-123', $raw);
        $this->assertArrayNotHasKey('api_key', $settings->toArray());
        $this->assertTrue($settings->toArray()['has_api_key']);
    }

    public function test_blank_api_key_keeps_the_existing_one_and_clear_flag_removes_it(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        app(PlateRecognitionSettings::class)->update(true, 'auto_blur', 70, 'platerecognizer', 'keep-me');

        // Ures mezovel mentes — a kulcs megmarad
        $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => true, 'mode' => 'auto_blur', 'min_confidence' => 75, 'api_key' => '',
        ])->assertRedirect();
        $this->assertSame('keep-me', app(PlateRecognitionSettings::class)->apiKey());

        // clear_api_key = true — torol
        $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => true, 'mode' => 'auto_blur', 'min_confidence' => 75, 'clear_api_key' => true,
        ])->assertRedirect();
        $this->assertFalse(app(PlateRecognitionSettings::class)->hasApiKey());
    }

    public function test_disabling_persists_as_falsey(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        app(PlateRecognitionSettings::class)->update(true, 'auto_blur', 70);

        $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => false,
            'mode' => 'auto_blur',
            'min_confidence' => 70,
        ])->assertRedirect();

        $this->assertFalse(app(PlateRecognitionSettings::class)->enabled());
    }

    public function test_rejects_invalid_mode_and_out_of_range_confidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/plate-recognition', [
            'enabled' => true,
            'mode' => 'delete_everything',
            'min_confidence' => 5,
        ])->assertSessionHasErrors(['mode', 'min_confidence']);
    }

    public function test_min_confidence_is_clamped_by_service(): void
    {
        SiteSetting::set('plate_recognition_min_confidence', '400');

        $this->assertSame(
            PlateRecognitionSettings::MIN_CONFIDENCE_CEILING,
            app(PlateRecognitionSettings::class)->minConfidence()
        );
    }
}
