<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PaymentSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CriticalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    public function test_page_is_superadmin_only(): void
    {
        $this->get('/admin/settings/critical')->assertRedirect('/login');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/admin/settings/critical')->assertForbidden();

        $this->actingAs($this->superadmin())->get('/admin/settings/critical')->assertOk();
    }

    public function test_index_renders_readiness_groups_and_payment_form(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/admin/settings/critical')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertNotEmpty($props['groups']);
        $this->assertArrayHasKey('stripe_has_secret', $props['payments']);
        $this->assertArrayHasKey('domain', $props['identity']);
        // A titkos kulcs SOHA nem megy ki propként.
        $this->assertArrayNotHasKey('stripe_secret', $props['payments']);
    }

    public function test_saving_payment_credentials_encrypts_them(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/settings/critical/payments', [
            'default_provider' => 'simplepay',
            'stripe_secret' => 'sk_live_secret',
            'simplepay_merchant' => 'MERCHANT1',
            'simplepay_secret_key' => 'sp-secret',
            'simplepay_sandbox' => false,
        ])->assertRedirect();

        $this->assertSame('sk_live_secret', Crypt::decryptString(SiteSetting::get('stripe_secret')));
        $this->assertSame('sp-secret', Crypt::decryptString(SiteSetting::get('simplepay_secret_key')));
        $this->assertSame('MERCHANT1', SiteSetting::get('simplepay_merchant'));
        $this->assertSame('simplepay', SiteSetting::get('payment_default_provider'));

        // Az ures titkos mezo nem torli a meglevot.
        $this->actingAs($this->superadmin())->put('/admin/settings/critical/payments', [
            'default_provider' => 'simplepay',
            'simplepay_merchant' => 'MERCHANT1',
        ])->assertRedirect();
        $this->assertSame('sk_live_secret', Crypt::decryptString(SiteSetting::get('stripe_secret')));
    }

    public function test_clear_checkbox_removes_a_secret(): void
    {
        SiteSetting::set('stripe_secret', Crypt::encryptString('sk_x'));

        $this->actingAs($this->superadmin())->put('/admin/settings/critical/payments', [
            'default_provider' => 'stripe',
            'clear' => ['stripe_secret'],
        ])->assertRedirect();

        $this->assertSame('', SiteSetting::get('stripe_secret'));
    }

    public function test_saved_payment_settings_reach_the_gateway_config(): void
    {
        SiteSetting::set('simplepay_merchant', 'M2');
        SiteSetting::set('simplepay_secret_key', Crypt::encryptString('k2'));

        app(PaymentSettings::class)->applyRuntimeConfig();

        $this->assertSame('M2', config('services.simplepay.merchant'));
        $this->assertSame('k2', config('services.simplepay.secret_key'));
    }

    public function test_identity_domain_is_validated_and_saved(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/settings/critical/identity', ['domain' => 'nem jó domain'])
            ->assertSessionHasErrors('domain');

        $this->actingAs($this->superadmin())->put('/admin/settings/critical/identity', ['domain' => 'kanyarfotozas.hu'])
            ->assertRedirect();

        $this->assertSame('kanyarfotozas.hu', SiteSetting::get('site_domain'));
    }

    public function test_identity_preview_and_apply_rewrites_platform_emails(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@kanyarfoto.hu']);

        $this->actingAs($sa)
            ->getJson('/admin/settings/critical/identity/preview?domain=kanyarfotozas.hu')
            ->assertOk()
            ->assertJsonPath('to', 'kanyarfotozas.hu')
            ->assertJsonPath('emails.0.new', 'superadmin@kanyarfotozas.hu');

        $this->actingAs($sa->fresh())
            ->post('/admin/settings/critical/identity/apply', ['domain' => 'kanyarfotozas.hu'])
            ->assertRedirect();

        $this->assertSame('superadmin@kanyarfotozas.hu', $sa->fresh()->email);
        $this->assertSame('kanyarfotozas.hu', SiteSetting::get('site_domain'));
    }

    public function test_identity_endpoints_are_superadmin_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->getJson('/admin/settings/critical/identity/preview?domain=pelda.hu')->assertForbidden();
        $this->actingAs($admin)->post('/admin/settings/critical/identity/apply', ['domain' => 'pelda.hu'])->assertForbidden();
    }
}
