<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\TwoFactor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** Beállít + megerősít egy 2FA-t egy usernek, visszaadja a titkot. */
    private function enrol(User $user): string
    {
        $tf = app(TwoFactor::class);
        $secret = $tf->generateSecret();
        $tf->enable($user, $secret);
        $tf->confirm($user->fresh(), $this->otp($secret));

        return $secret;
    }

    public function test_admin_can_enable_and_confirm_two_factor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/security/2fa')->assertRedirect();

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertFalse($admin->hasTwoFactorEnabled());
        $this->assertCount(TwoFactor::RECOVERY_CODE_COUNT, app(TwoFactor::class)->recoveryCodes($admin));

        $secret = app(TwoFactor::class)->secretFor($admin);
        $this->actingAs($admin)->post('/admin/settings/security/2fa/confirm', ['code' => $this->otp($secret)])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_with_bad_code_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/settings/security/2fa');

        $this->actingAs($admin->fresh())->post('/admin/settings/security/2fa/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_with_two_factor_redirects_to_challenge_without_authenticating(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $this->enrol($admin);

        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_challenge_with_valid_totp_completes_the_login(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $secret = $this->enrol($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $response = $this->post('/two-factor-challenge', ['code' => $this->otp($secret)]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_challenge_with_recovery_code_consumes_it(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $this->enrol($admin);
        $code = app(TwoFactor::class)->recoveryCodes($admin->fresh())[0];

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotContains($code, app(TwoFactor::class)->recoveryCodes($admin->fresh()));
    }

    public function test_challenge_with_a_bad_code_errors_and_stays_out(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $this->enrol($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_trusted_device_cookie_skips_the_challenge(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $secret = $this->enrol($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $response = $this->post('/two-factor-challenge', [
            'code' => $this->otp($secret),
            'remember_device' => true,
        ]);
        $response->assertRedirect('/admin/dashboard');
        $cookie = $response->getCookie(TwoFactor::TRUSTED_COOKIE, false)->getValue();
        $this->assertNotEmpty($cookie);

        $this->post('/logout');

        $again = $this->withUnencryptedCookie(TwoFactor::TRUSTED_COOKIE, $cookie)
            ->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $again->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_disable_requires_the_correct_password(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $this->enrol($admin);

        $this->actingAs($admin->fresh())->delete('/admin/settings/security/2fa', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin->fresh())->delete('/admin/settings/security/2fa', ['password' => 'password'])
            ->assertRedirect();
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_required_policy_forces_admins_without_2fa_to_the_security_page(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->enrol($superadmin);
        SiteSetting::set('two_factor_required', '1');

        $plainAdmin = User::factory()->admin()->create();

        $this->actingAs($plainAdmin)->get('/admin/dashboard')->assertRedirect(route('admin.settings.security'));
        $this->actingAs($plainAdmin)->get('/admin/settings/security')->assertOk();

        // A 2FA-val rendelkező superadmint nem zavarja.
        $this->actingAs($superadmin->fresh())->get('/admin/dashboard')->assertOk();
    }

    public function test_security_page_is_for_admins_not_photographers(): void
    {
        $this->actingAs(User::factory()->photographer()->create())
            ->get('/admin/settings/security')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/security')->assertOk();
    }

    public function test_superadmin_cannot_require_2fa_before_enabling_it_on_self(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/security/policy', ['required' => true])
            ->assertSessionHasErrors('required');
        $this->assertFalse(app(TwoFactor::class)->isRequiredForAdmins());
    }
}
