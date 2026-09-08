<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\FormGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HcaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function enableHcaptcha(): void
    {
        config([
            'services.hcaptcha.enabled' => true,
            'services.hcaptcha.site_key' => 'test-site-key',
            'services.hcaptcha.secret' => 'test-secret',
        ]);
    }

    /**
     * @return array{guard_token: string, guard_pow: int}
     */
    private function guardFields(): array
    {
        $guard = $this->get('/contact')->viewData('page')['props']['guard'];
        $pow = app(FormGuard::class)->solveProofOfWork($guard['pow']['salt'], $guard['pow']['bits']);
        $this->travel(5)->seconds();

        return ['guard_token' => $guard['token'], 'guard_pow' => $pow];
    }

    public function test_contact_page_exposes_the_hcaptcha_site_key_when_enabled(): void
    {
        $this->enableHcaptcha();

        $props = $this->get('/contact')->viewData('page')['props'];

        $this->assertTrue($props['hcaptcha']['enabled']);
        $this->assertSame('test-site-key', $props['hcaptcha']['site_key']);
    }

    public function test_submission_needs_a_valid_hcaptcha_token_when_enabled(): void
    {
        $this->enableHcaptcha();
        Http::fake(['*hcaptcha*' => Http::response(['success' => false])]);
        Mail::fake();

        $this->post('/contact', [
            'name' => 'Teszt', 'email' => 't@t.hu', 'subject' => 'x', 'message' => 'x',
            'h-captcha-response' => 'bad-token',
            ...$this->guardFields(),
        ])->assertSessionHasErrors('hcaptcha');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_valid_hcaptcha_token_lets_the_message_through_without_the_arithmetic_answer(): void
    {
        $this->enableHcaptcha();
        Http::fake(['*hcaptcha*' => Http::response(['success' => true])]);
        Mail::fake();

        $this->post('/contact', [
            'name' => 'Teszt', 'email' => 't@t.hu', 'subject' => 'x', 'message' => 'x',
            'h-captcha-response' => 'good-token',
            // NINCS guard_answer — a hCaptcha váltja
            ...$this->guardFields(),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', ['email' => 't@t.hu']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'hcaptcha') && $r['response'] === 'good-token');
    }

    public function test_disabled_hcaptcha_keeps_the_arithmetic_challenge(): void
    {
        // config default: kikapcsolva
        $props = $this->get('/contact')->viewData('page')['props'];
        $this->assertFalse($props['hcaptcha']['enabled']);
    }

    public function test_only_superadmin_can_configure_hcaptcha_and_the_secret_is_encrypted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/settings/critical/captcha', ['enabled' => true])->assertForbidden();

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin)->put('/admin/settings/critical/captcha', [
            'enabled' => true,
            'site_key' => 'my-site-key',
            'secret' => 'my-secret',
        ])->assertRedirect();

        $this->assertSame('1', SiteSetting::get('hcaptcha_enabled'));
        $this->assertSame('my-site-key', SiteSetting::get('hcaptcha_site_key'));
        $this->assertSame('my-secret', Crypt::decryptString(SiteSetting::get('hcaptcha_secret')));
    }
}
