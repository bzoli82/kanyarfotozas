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
        $this->assertSame('lift', $anim->cards());
        $this->assertSame('none', $anim->cardMedia());
        $this->assertTrue($anim->scrollReveal());
        $this->assertTrue($anim->counters());
        $this->assertTrue($anim->frostedHeader());
        $this->assertTrue($anim->progressBar());
        $this->assertTrue($anim->imageFade());
        $this->assertTrue($anim->heroGrain());
        $this->assertTrue($anim->buttonSheen());
        $this->assertTrue($anim->flyToCart());
        $this->assertTrue($anim->themeReveal());
        $this->assertTrue($anim->faqAccordion());
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
            'anim_cards' => 'tilt',
            'anim_cardmedia' => 'zoom',
            'anim_reveal' => false,
            'anim_counters' => true,
            'anim_header' => false,
            'anim_progress' => false,
            'anim_imgfade' => false,
            'anim_grain' => false,
            'anim_btnsheen' => false,
            'anim_flycart' => false,
            'anim_themereveal' => false,
            'anim_faq' => false,
        ])->assertRedirect();

        $anim = app(AnimationSettings::class);
        $this->assertSame('expressive', $anim->preset());
        $this->assertSame('slide', $anim->pageTransition());
        $this->assertSame('none', $anim->hero());
        $this->assertSame('tilt', $anim->cards());
        $this->assertSame('zoom', $anim->cardMedia());
        $this->assertFalse($anim->scrollReveal());
        $this->assertFalse($anim->frostedHeader());
        $this->assertFalse($anim->heroGrain());
        $this->assertFalse($anim->buttonSheen());
        $this->assertFalse($anim->flyToCart());
        $this->assertFalse($anim->themeReveal());
        $this->assertFalse($anim->faqAccordion());
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
            'anim_cards' => 'explode',
            'anim_cardmedia' => 'boom',
        ])->assertSessionHasErrors(['anim_preset', 'anim_cards', 'anim_cardmedia']);
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
