<?php

namespace Tests\Feature;

use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MailSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_update_mail_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/critical/mail', [
            'mailer' => 'smtp', 'host' => 'smtp.pelda.hu', 'encryption' => 'tls',
        ])->assertForbidden();
    }

    public function test_update_saves_and_encrypts_the_password(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/critical/mail', [
            'mailer' => 'smtp',
            'host' => 'smtp.pelda.hu',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'kf@pelda.hu',
            'password' => 'titok123',
            'from_address' => 'noreply@pelda.hu',
            'from_name' => 'KanyarFotózás',
        ])->assertRedirect();

        $this->assertSame('smtp', SiteSetting::get('mail_mailer'));
        $this->assertSame('smtp.pelda.hu', SiteSetting::get('mail_host'));
        $this->assertSame('titok123', Crypt::decryptString(SiteSetting::get('mail_password')));
        $this->assertSame('noreply@pelda.hu', SiteSetting::get('mail_from_address'));
    }

    public function test_empty_password_keeps_the_existing_one(): void
    {
        SiteSetting::set('mail_password', Crypt::encryptString('regi-jelszo'));
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/critical/mail', [
            'mailer' => 'smtp', 'host' => 'smtp.pelda.hu', 'encryption' => 'tls', 'password' => '',
        ])->assertRedirect();

        $this->assertSame('regi-jelszo', Crypt::decryptString(SiteSetting::get('mail_password')));
    }

    public function test_runtime_config_is_loaded_from_site_settings(): void
    {
        SiteSetting::set('mail_mailer', 'smtp');
        SiteSetting::set('mail_host', 'smtp.example.test');
        SiteSetting::set('mail_port', '2525');
        SiteSetting::set('mail_from_address', 'hello@example.test');

        app(MailSettings::class)->applyRuntimeConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('hello@example.test', config('mail.from.address'));
    }

    public function test_contact_reply_mail_uses_the_configured_reply_to(): void
    {
        SiteSetting::set('mail_reply_to', 'kapcsolat@pelda.hu');

        $superadmin = User::factory()->superadmin()->create();
        $message = ContactMessage::create([
            'name' => 'Vevő', 'email' => 'vevo@pelda.hu', 'subject' => 'Kérdés', 'message' => 'Szia',
        ]);

        $reply = ContactReply::create([
            'contact_message_id' => $message->id, 'author_id' => $superadmin->id, 'body' => 'Válasz', 'emailed' => true,
        ]);

        $envelope = (new ContactReplyMail($reply->fresh(['contactMessage', 'onBehalfOf'])))->envelope();

        $this->assertCount(1, $envelope->replyTo);
        $this->assertSame('kapcsolat@pelda.hu', $envelope->replyTo[0]->address);
    }

    public function test_test_mail_endpoint_sends_a_message(): void
    {
        Mail::fake();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->post('/admin/settings/critical/mail/test', ['to' => 'cel@pelda.hu'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
