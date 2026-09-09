<?php

namespace Tests\Feature\Public;

use App\Mail\ContactConfirmationMail;
use App\Mail\ContactNotificationMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\FormGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * A form GET-je egy aláírt FormGuard challenge-t ad; kiszámoljuk a helyes
     * választ + megoldjuk a proof-of-work puzzle-t, és „kivárjuk" a time-trapet.
     */
    private function payloadWithGuard(array $overrides = []): array
    {
        $guard = $this->get('/contact')->viewData('page')['props']['guard'];

        preg_match('/(\d+)\s*\+\s*(\d+)/', $guard['question'], $m);
        $pow = app(FormGuard::class)->solveProofOfWork($guard['pow']['salt'], $guard['pow']['bits']);

        $this->travel(5)->seconds();

        return array_merge([
            'name' => 'Teszt Elek',
            'email' => 'teszt@example.com',
            'subject' => 'Kérdés a letöltésről',
            'message' => 'Nem találom a letöltési linket.',
            'guard_token' => $guard['token'],
            'guard_answer' => (int) $m[1] + (int) $m[2],
            'guard_pow' => $pow,
        ], $overrides);
    }

    public function test_contact_page_renders_with_a_guard_challenge(): void
    {
        $props = $this->get('/contact')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($props['guard']['token']);
        $this->assertNotEmpty($props['guard']['question']);
        $this->assertGreaterThan(0, $props['guard']['pow']['bits']);
    }

    public function test_valid_submission_stores_message_and_sends_both_mails(): void
    {
        Mail::fake();
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->post('/contact', $this->payloadWithGuard());

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'teszt@example.com',
            'status' => ContactMessage::STATUS_NEW,
        ]);
        Mail::assertQueued(ContactConfirmationMail::class, fn ($m) => $m->hasTo('teszt@example.com'));
        Mail::assertQueued(ContactNotificationMail::class, fn ($m) => $m->hasTo($superadmin->email));
    }

    public function test_wrong_arithmetic_answer_is_rejected(): void
    {
        Mail::fake();

        $this->post('/contact', $this->payloadWithGuard(['guard_answer' => 999999]))
            ->assertSessionHasErrors('guard_answer');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_too_fast_submission_is_rejected_by_the_time_trap(): void
    {
        Mail::fake();

        $guard = $this->get('/contact')->viewData('page')['props']['guard'];
        preg_match('/(\d+)\s*\+\s*(\d+)/', $guard['question'], $m);
        $pow = app(FormGuard::class)->solveProofOfWork($guard['pow']['salt'], $guard['pow']['bits']);

        // NINCS ->travel() — azonnali beküldés.
        $this->post('/contact', [
            'name' => 'Bot', 'email' => 'b@b.hu', 'subject' => 'Gyors kérdés', 'message' => 'Ez egy elég hosszú próbaüzenet.',
            'guard_token' => $guard['token'], 'guard_answer' => (int) $m[1] + (int) $m[2], 'guard_pow' => $pow,
        ])->assertSessionHasErrors('guard');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_invalid_proof_of_work_is_rejected(): void
    {
        $this->post('/contact', $this->payloadWithGuard(['guard_pow' => 0]))
            ->assertSessionHasErrors('guard');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_solved_token_cannot_be_replayed(): void
    {
        Mail::fake();
        $payload = $this->payloadWithGuard();

        $this->post('/contact', $payload)->assertRedirect();
        $this->post('/contact', $payload)->assertSessionHasErrors('guard');

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $payload = $this->payloadWithGuard();
        // A vezérszám átírása a signed payloadban (a válasz „meghamisítása").
        $payload['guard_token'] = substr($payload['guard_token'], 0, 5).'x'.substr($payload['guard_token'], 6);

        $this->post('/contact', $payload)->assertSessionHasErrors('guard');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_honeypot_silently_discards_bot_submissions(): void
    {
        Mail::fake();

        $response = $this->post('/contact', $this->payloadWithGuard(['nickname' => 'spammer']));

        $response->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_submission_requires_valid_fields(): void
    {
        $this->post('/contact', $this->payloadWithGuard(['email' => 'not-an-email', 'message' => '']))
            ->assertSessionHasErrors(['email', 'message']);
    }
}
