<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ThemeSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_theme_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/admin/settings/theme')->assertForbidden();
        $this->actingAs($photographer)->get('/admin/settings/theme')->assertForbidden();
        $this->actingAs($superadmin)->get('/admin/settings/theme')->assertOk();
    }

    public function test_superadmin_can_update_theme_settings(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'default',
            'mode' => 'light',
            'accent_color' => '#00aaff',
            'border_radius' => 4,
            'font_family' => 'Poppins',
        ]);

        $response->assertRedirect();
        $this->assertSame('light', SiteSetting::get('theme_mode'));
        $this->assertSame('#00aaff', SiteSetting::get('accent_color'));
        $this->assertSame('4', SiteSetting::get('border_radius'));
        $this->assertSame('Poppins', SiteSetting::get('font_family'));
    }

    public function test_selecting_a_preset_drives_the_resolved_palette(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'ocean',
            'mode' => 'dark',
            'accent_color' => '#3b82f6',
            'border_radius' => 12,
            'font_family' => 'Inter',
        ])->assertRedirect();

        $palettes = app(ThemeSettings::class)->resolvedPalettes();
        $this->assertSame('ocean', app(ThemeSettings::class)->preset());
        $this->assertSame(ThemeSettings::PRESETS['ocean']['dark']['surface_0'], $palettes['dark']['surface_0']);
        $this->assertSame('#3b82f6', $palettes['light']['accent']);
    }

    public function test_custom_preset_stores_and_resolves_per_mode_colours(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'custom',
            'mode' => 'dark',
            'accent_color' => '#12ab34',
            'border_radius' => 8,
            'font_family' => 'Inter',
            'custom' => [
                'light' => ['surface_0' => '#fefefe', 'surface_1' => '#ffffff', 'surface_2' => '#f0f0f0', 'border' => '#dddddd', 'content' => '#111111', 'muted' => '#888888'],
                'dark' => ['surface_0' => '#010203', 'surface_1' => '#0a0b0c', 'surface_2' => '#141516', 'border' => '#232425', 'content' => '#eeeeee', 'muted' => '#999999'],
            ],
        ])->assertRedirect();

        $palettes = app(ThemeSettings::class)->resolvedPalettes();
        $this->assertSame('#010203', $palettes['dark']['surface_0']);
        $this->assertSame('#fefefe', $palettes['light']['surface_0']);
    }

    public function test_custom_preset_rejects_non_hex_colours(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'custom',
            'mode' => 'dark',
            'accent_color' => '#12ab34',
            'border_radius' => 8,
            'font_family' => 'Inter',
            'custom' => ['dark' => ['surface_0' => 'red']],
        ])->assertSessionHasErrors('custom.dark.surface_0');
    }

    public function test_default_preset_matches_the_current_look(): void
    {
        $palettes = app(ThemeSettings::class)->resolvedPalettes();

        $this->assertSame('default', app(ThemeSettings::class)->preset());
        $this->assertSame('#0d0d0d', $palettes['dark']['surface_0']);
        $this->assertSame('#e63946', $palettes['dark']['accent']);
    }

    public function test_update_rejects_invalid_mode_color_and_font(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'default',
            'mode' => 'purple',
            'accent_color' => 'not-a-color',
            'border_radius' => 4,
            'font_family' => 'Comic Sans',
        ]);

        $response->assertSessionHasErrors(['mode', 'accent_color', 'font_family']);
    }

    public function test_theme_settings_service_falls_back_to_defaults_when_unset(): void
    {
        $theme = app(ThemeSettings::class);

        $this->assertSame(ThemeSettings::DEFAULT_MODE, $theme->mode());
        $this->assertSame(ThemeSettings::DEFAULT_ACCENT_COLOR, $theme->accentColor());
        $this->assertSame(ThemeSettings::DEFAULT_BORDER_RADIUS, $theme->borderRadius());
        $this->assertSame(ThemeSettings::DEFAULT_FONT, $theme->fontKey());
    }

    public function test_accent_hover_color_is_a_darkened_version_of_accent_color(): void
    {
        SiteSetting::set('accent_color', '#e63946');

        $theme = app(ThemeSettings::class);

        $this->assertNotSame('#e63946', $theme->accentHoverColor());
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $theme->accentHoverColor());
    }

    public function test_shared_inertia_prop_exposes_theme_mode_to_every_page(): void
    {
        SiteSetting::set('theme_mode', 'light');

        $response = $this->get('/events');

        $this->assertSame('light', $response->viewData('page')['props']['themeMode']);
    }
}
