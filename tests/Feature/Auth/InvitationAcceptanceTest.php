<?php

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_valid_invitation_page_renders(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->get("/invitations/{$invitation->token}");

        $response->assertOk();
        $this->assertSame($invitation->email, $response->viewData('page')['props']['email']);
    }

    public function test_accepted_invitation_page_is_not_found(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->get("/invitations/{$invitation->token}")->assertNotFound();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get('/invitations/does-not-exist')->assertNotFound();
    }

    public function test_accepting_invitation_creates_user_and_logs_in(): void
    {
        $invitation = Invitation::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 75]);

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'uj-jelszo-123',
            'password_confirmation' => 'uj-jelszo-123',
            'agreement_accepted' => true,
        ]);

        $response->assertRedirect('/photographer/dashboard');

        $user = User::query()->where('email', $invitation->email)->firstOrFail();
        $this->assertSame($invitation->name, $user->name);
        $this->assertSame(75, $user->revenue_share_percent);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->agreed_terms_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invitation_cannot_be_accepted_without_the_agreement(): void
    {
        $invitation = Invitation::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);

        $this->post("/invitations/{$invitation->token}", [
            'password' => 'uj-jelszo-123',
            'password_confirmation' => 'uj-jelszo-123',
        ])->assertSessionHasErrors('agreement_accepted');

        $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'uj-jelszo-123',
            'password_confirmation' => 'uj-jelszo-123',
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
    }

    public function test_already_accepted_invitation_cannot_be_reaccepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->post("/invitations/{$invitation->token}", [
            'password' => 'uj-jelszo-123',
            'password_confirmation' => 'uj-jelszo-123',
        ])->assertNotFound();
    }
}
