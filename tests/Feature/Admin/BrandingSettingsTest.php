<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SiteBranding;
use App\Services\WatermarkSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_branding_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/branding')->assertForbidden();
        $this->actingAs(User::factory()->photographer()->create())->get('/admin/settings/branding')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/branding')->assertOk();
    }

    public function test_defaults_are_the_hardcoded_brand(): void
    {
        $branding = app(SiteBranding::class);

        $this->assertSame(SiteBranding::DEFAULT_NAME, $branding->name());
        $this->assertSame('KANYAR', $branding->logoLead());
        $this->assertSame('FOTÓS', $branding->logoTail());
    }

    public function test_saving_the_name_also_overwrites_the_watermark_text(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/branding', [
            'name' => 'Ívfotó',
            'logo_lead' => 'ÍV',
            'logo_tail' => 'FOTÓ',
        ])->assertRedirect();

        $this->assertSame('Ívfotó', app(SiteBranding::class)->name());
        $this->assertSame('ÍV', app(SiteBranding::class)->logoLead());
        $this->assertSame('ÍVFOTÓ', app(WatermarkSettings::class)->text());
    }

    public function test_branding_is_shared_to_every_inertia_page(): void
    {
        app(SiteBranding::class)->update('Kanyar Média', 'KANYAR', 'MÉDIA');

        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame('Kanyar Média', $props['branding']['name']);
        $this->assertSame('MÉDIA', $props['branding']['logo_tail']);
        $this->assertSame('Kanyar Média', $props['appName']);
    }

    public function test_blade_title_and_mail_signature_use_the_branding_name(): void
    {
        app(SiteBranding::class)->update('Próba Név', 'PRÓBA', 'NÉV');

        // A blade cím (a SEO-composer a márkanévvel kezdődő címet állít elő)
        $html = $this->get('/login')->getContent();
        $this->assertMatchesRegularExpression('/<title inertia>Próba Név\b.*<\/title>/u', $html);
    }

    public function test_empty_logo_tail_is_allowed_for_a_single_colour_logo(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/branding', [
            'name' => 'Egyszínű',
            'logo_lead' => 'EGYSZÍNŰ',
            'logo_tail' => '',
        ])->assertRedirect();

        $this->assertSame('', app(SiteBranding::class)->logoTail());
    }
}
