<?php

namespace Tests\Feature\Admin;

use App\Mail\PhotographerInvitationMail;
use App\Mail\TemporaryPasswordMail;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhotographerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_photographer_list(): void
    {
        $admin = User::factory()->admin()->create();
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($admin)->get('/admin/photographers')->assertForbidden();
        $this->actingAs($photographer)->get('/admin/photographers')->assertForbidden();
        $this->actingAs($superadmin)->get('/admin/photographers')->assertOk();
    }

    public function test_index_lists_photographers_and_admins_with_search_and_filters(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create(['name' => 'Kovács János', 'is_active' => true]);
        User::factory()->photographer()->create(['name' => 'Nagy Béla', 'is_active' => false]);

        $response = $this->actingAs($superadmin)->get('/admin/photographers?search=Kovács');

        $names = collect($response->viewData('page')['props']['users'])->pluck('name');
        $this->assertTrue($names->contains('Kovács János'));
        $this->assertFalse($names->contains('Nagy Béla'));
        $this->assertSame($photographer->id, $response->viewData('page')['props']['users'][0]['id']);
    }

    public function test_invite_creates_invitation_and_sends_email(): void
    {
        Mail::fake();
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->post('/admin/photographers/invite', [
            'name' => 'Új Fotós',
            'email' => 'uj.fotos@example.com',
            'role' => 'photographer',
            'revenue_share_percent' => 65,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invitations', ['email' => 'uj.fotos@example.com', 'revenue_share_percent' => 65]);
        Mail::assertQueued(PhotographerInvitationMail::class, fn ($mail) => $mail->invitation->email === 'uj.fotos@example.com');
    }

    public function test_invite_rejects_email_already_registered(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $existing = User::factory()->photographer()->create();

        $response = $this->actingAs($superadmin)->post('/admin/photographers/invite', [
            'name' => 'Duplikátum',
            'email' => $existing->email,
            'role' => 'photographer',
            'revenue_share_percent' => 70,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_resend_invitation_reissues_token_and_resends_email(): void
    {
        Mail::fake();
        $superadmin = User::factory()->superadmin()->create();
        $invitation = Invitation::factory()->expired()->create();
        $oldToken = $invitation->token;

        $response = $this->actingAs($superadmin)->post("/admin/photographers/invitations/{$invitation->id}/resend");

        $response->assertRedirect();
        $this->assertNotSame($oldToken, $invitation->fresh()->token);
        $this->assertTrue($invitation->fresh()->expires_at->isFuture());
        Mail::assertQueued(PhotographerInvitationMail::class);
    }

    public function test_cancel_invitation_deletes_pending_invite(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $invitation = Invitation::factory()->create();

        $this->actingAs($superadmin)->delete("/admin/photographers/invitations/{$invitation->id}")->assertRedirect();

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }

    public function test_show_page_exposes_kpis_events_sales_and_activity(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();
        Media::factory()->photo()->create(['photographer_id' => $photographer->id]);
        Media::factory()->video()->create(['photographer_id' => $photographer->id]);

        $response = $this->actingAs($superadmin)->get("/admin/photographers/{$photographer->id}");

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame(1, $props['kpis']['uploaded_photos']);
        $this->assertSame(1, $props['kpis']['uploaded_videos']);
        $this->assertArrayHasKey('revenueTrend', $props);
        $this->assertArrayHasKey('activity', $props);
    }

    public function test_update_saves_profile_and_revenue_share(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();

        $response = $this->actingAs($superadmin)->put("/admin/photographers/{$photographer->id}", [
            'name' => 'Frissített Név',
            'email' => $photographer->email,
            'role' => 'photographer',
            'revenue_share_percent' => 80,
            'is_active' => false,
            'is_public' => true,
            'bio' => 'Kanyar-vadász.',
        ]);

        $response->assertRedirect();
        $photographer->refresh();
        $this->assertSame('Frissített Név', $photographer->name);
        $this->assertSame(80, $photographer->revenue_share_percent);
        $this->assertFalse($photographer->is_active);
        $this->assertTrue($photographer->is_public);
    }

    public function test_reset_password_emails_temporary_password_and_changes_it(): void
    {
        Mail::fake();
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create(['password' => 'password']);
        $oldHash = $photographer->password;

        $this->actingAs($superadmin)->post("/admin/photographers/{$photographer->id}/reset-password")->assertRedirect();

        $this->assertNotSame($oldHash, $photographer->fresh()->password);
        Mail::assertQueued(TemporaryPasswordMail::class, fn ($mail) => $mail->user->id === $photographer->id);
    }

    public function test_destroy_deletes_photographer_without_media(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($superadmin)->delete("/admin/photographers/{$photographer->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $photographer->id]);
    }

    public function test_destroy_refuses_to_delete_photographer_with_media(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();
        Media::factory()->create(['photographer_id' => $photographer->id]);

        $this->actingAs($superadmin)->delete("/admin/photographers/{$photographer->id}")->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $photographer->id]);
    }
}
