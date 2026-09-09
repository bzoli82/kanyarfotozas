<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AnimationSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_defaults_when_unset(): void
    {
        $anim = app(AnimationSettings::class);

        $this->assertTrue($anim->enabled());
        $this->assertSame('standard', $anim->preset());
        $this->assertSame('fade', $anim->pageTransition());
        $this->assertSame('full', $anim->hero());
        $this->assertTrue($anim->scrollReveal());
        $this->assertTrue($anim->counters());
        $this->assertSame('480ms', $anim->resolvedVars()['--anim-duration']);
    }

    public function test_disabled_zeroes_the_css_vars(): void
    {
        SiteSetting::set('anim_enabled', '0');

        $vars = app(AnimationSettings::class)->resolvedVars();

        $this->assertSame('0ms', $vars['--anim-duration']);
        $this->assertSame('0px', $vars['--anim-distance']);
    }

    public function test_superadmin_saves_animation_settings_via_theme_form(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'default',
            'mode' => 'dark',
            'accent_color' => '#e63946',
            'border_radius' => 12,
            'font_family' => 'Inter',
            'anim_enabled' => true,
            'anim_preset' => 'expressive',
            'anim_page' => 'slide',
            'anim_hero' => 'none',
            'anim_reveal' => false,
            'anim_counters' => true,
        ])->assertRedirect();

        $anim = app(AnimationSettings::class);
        $this->assertSame('expressive', $anim->preset());
        $this->assertSame('slide', $anim->pageTransition());
        $this->assertSame('none', $anim->hero());
        $this->assertFalse($anim->scrollReveal());
        $this->assertSame('720ms', $anim->resolvedVars()['--anim-duration']);
    }

    public function test_invalid_preset_is_rejected(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'default',
            'mode' => 'dark',
            'accent_color' => '#e63946',
            'border_radius' => 12,
            'font_family' => 'Inter',
            'anim_preset' => 'wobble',
        ])->assertSessionHasErrors('anim_preset');
    }

    public function test_partial_put_does_not_wipe_animation_settings(): void
    {
        SiteSetting::set('anim_preset', 'subtle');
        $superadmin = User::factory()->superadmin()->create();

        // A régi téma-tesztek stílusú, anim-mezők nélküli PUT.
        $this->actingAs($superadmin)->put('/admin/settings/theme', [
            'preset' => 'default',
            'mode' => 'light',
            'accent_color' => '#00aaff',
            'border_radius' => 4,
            'font_family' => 'Poppins',
        ])->assertRedirect();

        $this->assertSame('subtle', app(AnimationSettings::class)->preset());
    }

    public function test_shared_inertia_prop_exposes_animation_settings(): void
    {
        SiteSetting::set('anim_page', 'none');

        $props = $this->get('/events')->viewData('page')['props'];

        $this->assertSame('none', $props['animation']['page']);
        $this->assertArrayHasKey('enabled', $props['animation']);
    }
}
