<?php

namespace Tests\Feature\Public;

use App\Mail\ContactNotificationMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\FormGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhotographerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function payloadWithGuard(array $overrides = []): array
    {
        $guard = $this->get('/csatlakozz')->viewData('page')['props']['guard'];

        preg_match('/(\d+)\s*\+\s*(\d+)/', $guard['question'], $m);
        $pow = app(FormGuard::class)->solveProofOfWork($guard['pow']['salt'], $guard['pow']['bits']);

        $this->travel(5)->seconds();

        return array_merge([
            'name' => 'Kovács Fotós',
            'email' => 'foto@example.com',
            'portfolio' => 'instagram.com/kovacsfoto',
            'region' => 'Hungaroring, Pannónia-ring',
            'shoots' => 'rally, gyorsulási',
            'message' => 'Öt éve fotózok motorsport-eseményeken, Canon R6-tal.',
            'guard_token' => $guard['token'],
            'guard_answer' => (int) $m[1] + (int) $m[2],
            'guard_pow' => $pow,
        ], $overrides);
    }

    public function test_application_page_renders(): void
    {
        $this->get('/csatlakozz')->assertOk()->assertInertia(fn ($page) => $page->component('Info/PhotographerApplication'));
    }

    public function test_valid_application_is_stored_as_a_typed_contact_message(): void
    {
        Mail::fake();
        User::factory()->superadmin()->create();

        $this->post('/csatlakozz', $this->payloadWithGuard())->assertRedirect()->assertSessionHasNoErrors();

        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame(ContactMessage::TYPE_PHOTOGRAPHER_APPLICATION, $message->contact_type);
        $this->assertStringContainsString('instagram.com/kovacsfoto', $message->message);
        $this->assertStringContainsString('Hungaroring', $message->message);

        Mail::assertQueued(ContactNotificationMail::class);
    }

    public function test_admin_can_filter_messages_to_applications_only(): void
    {
        ContactMessage::create([
            'name' => 'A', 'email' => 'a@example.com', 'subject' => 's', 'message' => 'm',
            'contact_type' => ContactMessage::TYPE_PHOTOGRAPHER_APPLICATION, 'status' => ContactMessage::STATUS_NEW,
        ]);
        ContactMessage::create([
            'name' => 'B', 'email' => 'b@example.com', 'subject' => 's', 'message' => 'm',
            'contact_type' => ContactMessage::TYPE_SUPPORT, 'status' => ContactMessage::STATUS_NEW,
        ]);

        $props = $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/messages?filter=applications')
            ->viewData('page')['props'];

        $this->assertCount(1, $props['threads']);
        $this->assertSame(1, $props['counts']['applications']);
    }
}
