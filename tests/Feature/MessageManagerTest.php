<?php

namespace Tests\Feature;

use App\Mail\ContactConfirmationMail;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\User;
use App\Support\FormGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    public function test_admin_sees_every_thread_including_photographer_directed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);

        $support = ContactMessage::factory()->create(['photographer_id' => null, 'subject' => 'Support kérdés']);
        $forPhotog = ContactMessage::factory()->forPhotographer($photographer)->create(['subject' => 'Fotós kérdés']);

        $this->actingAs($admin)
            ->get('/admin/messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Messages/Index')
                ->where('threads', fn ($threads) => collect($threads)->pluck('id')->contains($support->id)
                    && collect($threads)->pluck('id')->contains($forPhotog->id)));
    }

    public function test_admin_reply_emails_the_sender_and_records_the_thread(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $message = ContactMessage::factory()->create(['email' => 'kerdezo@example.com']);

        $this->actingAs($admin)
            ->post("/admin/messages/{$message->id}/reply", ['body' => 'Köszönjük, hamarosan intézzük.'])
            ->assertRedirect();

        $this->assertDatabaseHas('contact_replies', [
            'contact_message_id' => $message->id,
            'author_id' => $admin->id,
            'on_behalf_of_id' => null,
        ]);
        $this->assertSame(ContactMessage::STATUS_IN_PROGRESS, $message->fresh()->status);
        Mail::assertQueued(ContactReplyMail::class, fn ($m) => $m->hasTo('kerdezo@example.com'));
    }

    public function test_admin_can_reply_on_behalf_of_the_thread_photographer(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'name' => 'Kovács Béla']);
        $message = ContactMessage::factory()->forPhotographer($photographer)->create();

        $this->actingAs($admin)
            ->post("/admin/messages/{$message->id}/reply", [
                'body' => 'Szia, a fotó a 3. kanyarban készült.',
                'on_behalf_of_id' => $photographer->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contact_replies', [
            'contact_message_id' => $message->id,
            'author_id' => $admin->id,
            'on_behalf_of_id' => $photographer->id,
        ]);
    }

    public function test_admin_cannot_reply_on_behalf_of_an_unrelated_photographer(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $threadPhotog = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);
        $otherPhotog = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);
        $message = ContactMessage::factory()->forPhotographer($threadPhotog)->create();

        $this->actingAs($admin)
            ->post("/admin/messages/{$message->id}/reply", [
                'body' => 'x',
                'on_behalf_of_id' => $otherPhotog->id,
            ])
            ->assertStatus(422);
    }

    public function test_photographer_only_sees_and_answers_their_own_threads(): void
    {
        $mine = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);
        $other = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);

        $myThread = ContactMessage::factory()->forPhotographer($mine)->create();
        $otherThread = ContactMessage::factory()->forPhotographer($other)->create();

        $this->actingAs($mine)
            ->get('/photographer/messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('threads', fn ($threads) => collect($threads)->pluck('id')->all() === [$myThread->id]));

        $this->actingAs($mine)
            ->post("/photographer/messages/{$otherThread->id}/reply", ['body' => 'x'])
            ->assertNotFound();

        $this->actingAs($mine)
            ->post("/photographer/messages/{$myThread->id}/reply", ['body' => 'Válaszolok.'])
            ->assertRedirect();

        Mail::assertQueued(ContactReplyMail::class);
    }

    /**
     * A /media/{id} oldal aláírt FormGuard challenge-t ad — feloldjuk.
     */
    private function guardPayload(Media $media): array
    {
        $guard = $this->get("/media/{$media->id}")->viewData('page')['props']['contactGuard'];

        preg_match('/(\d+)\s*\+\s*(\d+)/', $guard['question'], $m);
        $pow = app(FormGuard::class)->solveProofOfWork($guard['pow']['salt'], $guard['pow']['bits']);

        $this->travel(5)->seconds();

        return [
            'guard_token' => $guard['token'],
            'guard_answer' => (int) $m[1] + (int) $m[2],
            'guard_pow' => $pow,
        ];
    }

    public function test_visitor_can_ask_the_photographer_from_the_media_page(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);
        $media = Media::factory()->create([
            'photographer_id' => $photographer->id,
            'status' => Media::STATUS_READY,
        ]);

        $this->post("/media/{$media->id}/question", [
            'name' => 'Látogató Lajos',
            'email' => 'lajos@example.com',
            'message' => 'Van ebből több kép a bokszutcából?',
            ...$this->guardPayload($media),
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'lajos@example.com',
            'contact_type' => ContactMessage::TYPE_PHOTOGRAPHER,
            'photographer_id' => $photographer->id,
            'event_id' => $media->event_id,
        ]);
        Mail::assertQueued(ContactConfirmationMail::class, fn ($m) => $m->hasTo('lajos@example.com'));
    }

    public function test_media_question_rejects_a_wrong_arithmetic_answer(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);
        $media = Media::factory()->create(['photographer_id' => $photographer->id, 'status' => Media::STATUS_READY]);

        $this->post("/media/{$media->id}/question", [
            'name' => 'X',
            'email' => 'x@example.com',
            'message' => 'kérdés',
            ...$this->guardPayload($media),
            'guard_answer' => 999999,
        ])->assertSessionHasErrors('guard_answer');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_photographer_messages_are_forbidden_for_non_photographers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/photographer/messages')->assertForbidden();
    }
}
