<?php

namespace Tests\Feature\Auth;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_admin_can_authenticate_and_is_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create([
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_photographer_is_redirected_to_photographer_dashboard(): void
    {
        $user = User::factory()->photographer()->create([
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/photographer/dashboard');
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->admin()->create([
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_invalid_password_is_logged_as_a_failed_login_attempt(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertSame(1, FailedLoginAttempt::query()->where('email', $user->email)->count());
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->admin()->create([
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_unknown_email_login_attempt_is_also_logged(): void
    {
        $this->post('/login', ['email' => 'nincs-ilyen@example.com', 'password' => 'whatever']);

        $this->assertSame(1, FailedLoginAttempt::query()->where('email', 'nincs-ilyen@example.com')->count());
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
