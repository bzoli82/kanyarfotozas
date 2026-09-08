<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function attemptLogin(string $email, string $password): TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_identity_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password']);

        for ($i = 0; $i < LoginRequest::MAX_PER_IDENTITY; $i++) {
            $this->attemptLogin($user->email, 'wrong');
        }

        // A helyes jelszo is elutasitasra kerul, amig az idozar tart.
        $response = $this->attemptLogin($user->email, 'password');

        $this->assertGuest();
        $response->assertInvalid(['email' => 'Túl sok sikertelen']);
    }

    public function test_successful_login_clears_the_limiter_and_failed_attempts(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password']);

        $this->attemptLogin($user->email, 'wrong');
        $this->attemptLogin($user->email, 'wrong');
        $this->assertSame(2, FailedLoginAttempt::where('email', $user->email)->count());

        $this->attemptLogin($user->email, 'password')->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, FailedLoginAttempt::where('email', $user->email)->count());
    }

    public function test_account_is_locked_after_many_failures_regardless_of_ip(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password']);

        for ($i = 0; $i < LoginRequest::ACCOUNT_LOCK_THRESHOLD; $i++) {
            FailedLoginAttempt::create(['email' => $user->email, 'ip_hash' => hash('sha256', "10.0.0.{$i}")]);
        }

        // Uj IP + helyes jelszo -> a fiok-szintu zar miatt meg mindig tiltva.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->attemptLogin($user->email, 'password');

        $this->assertGuest();
        $response->assertInvalid(['email' => 'ideiglenesen zároltuk']);
    }

    public function test_old_failures_do_not_lock_the_account(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password']);

        for ($i = 0; $i < LoginRequest::ACCOUNT_LOCK_THRESHOLD + 2; $i++) {
            $row = new FailedLoginAttempt(['email' => $user->email, 'ip_hash' => 'x']);
            $row->created_at = now()->subMinutes(LoginRequest::ACCOUNT_LOCK_MINUTES + 5);
            $row->save();
        }

        $this->attemptLogin($user->email, 'password')->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_returns_a_generic_error(): void
    {
        $this->attemptLogin('nincs-ilyen@example.com', 'whatever')
            ->assertSessionHasErrors(['email' => 'Hibás e-mail cím vagy jelszó.']);
    }

    public function test_inactive_account_with_correct_password_is_told_and_stays_out(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password', 'is_active' => false]);

        $response = $this->attemptLogin($user->email, 'password');

        $this->assertGuest();
        $response->assertInvalid(['email' => 'inaktív']);
    }

    public function test_lockout_event_is_dispatched_when_rate_limited(): void
    {
        Event::fake([Lockout::class]);
        $user = User::factory()->admin()->create(['password' => 'password']);

        for ($i = 0; $i < LoginRequest::MAX_PER_IDENTITY + 1; $i++) {
            $this->attemptLogin($user->email, 'wrong');
        }

        Event::assertDispatched(Lockout::class);
    }
}
