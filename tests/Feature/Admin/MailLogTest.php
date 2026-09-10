<?php

namespace Tests\Feature\Admin;

use App\Models\SentEmail;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_successfully_sent_email_is_logged(): void
    {
        config(['mail.default' => 'array']);

        Mail::raw('teszt', fn ($m) => $m->to('vevo@example.com')->subject('Letöltési link'));

        $this->assertDatabaseHas('sent_emails', [
            'recipient' => 'vevo@example.com',
            'subject' => 'Letöltési link',
        ]);
    }

    public function test_superadmin_can_search_the_mail_log(): void
    {
        SentEmail::create(['recipient' => 'alfa@example.com', 'subject' => 'Rendelés', 'created_at' => now()]);
        SentEmail::create(['recipient' => 'beta@example.com', 'subject' => 'Emlékeztető', 'created_at' => now()]);

        $props = $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/mail-log?q=alfa')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $props['emails']['data']);
        $this->assertSame('alfa@example.com', $props['emails']['data'][0]['recipient']);
        $this->assertSame(2, $props['total']);
    }

    public function test_photographer_cannot_view_the_mail_log(): void
    {
        $this->actingAs(User::factory()->photographer()->create())
            ->get('/admin/mail-log')
            ->assertForbidden();
    }
}
