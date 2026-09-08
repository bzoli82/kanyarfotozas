<?php

namespace Tests\Feature\Admin;

use App\Mail\ContactConfirmationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MailTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_mail_templates(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/mail')->assertForbidden();
        $this->actingAs(User::factory()->photographer()->create())->get('/admin/settings/mail')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/mail')->assertOk();
    }

    public function test_superadmin_can_override_a_template_and_it_persists(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/mail/contact_confirmation', [
            'subject' => 'Egyedi tárgy :name',
            'heading' => 'Egyedi cím',
            'intro' => 'Szia :name, egyedi bevezető.',
            'outro' => '',
            'signature' => 'Üdv, :app_name',
        ]);

        $response->assertRedirect();

        $raw = MailTemplates::raw('contact_confirmation');
        $this->assertSame('Egyedi tárgy :name', $raw['subject']);
        $this->assertSame('Egyedi cím', $raw['heading']);
    }

    public function test_override_rejects_missing_subject_or_heading(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/mail/order_confirmation', [
            'subject' => '',
            'heading' => '',
        ])->assertSessionHasErrors(['subject', 'heading']);
    }

    public function test_unknown_template_key_is_404(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/mail/nope', [
            'subject' => 'x',
            'heading' => 'y',
        ])->assertNotFound();
    }

    public function test_reset_restores_default(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        MailTemplates::update('temporary_password', [
            'subject' => 'Custom',
            'heading' => 'Custom',
            'intro' => 'Custom',
            'outro' => '',
            'signature' => '',
        ]);

        $this->actingAs($superadmin)->post('/admin/settings/mail/temporary_password/reset')->assertRedirect();

        $this->assertSame(
            MailTemplates::registry()['temporary_password']['defaults']['subject'],
            MailTemplates::raw('temporary_password')['subject']
        );
    }

    public function test_mailable_subject_reflects_override_with_placeholders_substituted(): void
    {
        MailTemplates::update('order_confirmation', [
            'subject' => 'Rendelés :order_id kész',
            'heading' => 'Kész',
            'intro' => 'Bevezető',
            'outro' => '',
            'signature' => '',
        ]);

        $media = Media::factory()->photo()->create(['status' => Media::STATUS_READY]);
        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();

        $mailable = new OrderConfirmationMail($order->fresh());

        $this->assertSame("Rendelés {$order->id} kész", $mailable->envelope()->subject);
    }

    public function test_mailable_body_uses_overridden_heading_and_intro(): void
    {
        MailTemplates::update('contact_confirmation', [
            'subject' => 'Tárgy',
            'heading' => 'EGYEDI FEJLÉC SZÖVEG',
            'intro' => 'Egyedi bevezető mondat.',
            'outro' => '',
            'signature' => 'Üdv, :app_name',
        ]);

        $message = ContactMessage::factory()->create(['name' => 'Teszt Elek']);

        $rendered = (new ContactConfirmationMail($message))->render();

        $this->assertStringContainsString('EGYEDI FEJLÉC SZÖVEG', $rendered);
        $this->assertStringContainsString('Egyedi bevezető mondat.', $rendered);
        $this->assertStringContainsString(config('app.name'), $rendered);
    }

    public function test_defaults_apply_when_nothing_overridden(): void
    {
        $this->assertSame(
            'Ideiglenes jelszó',
            MailTemplates::raw('temporary_password')['heading']
        );
        $this->assertNull(SiteSetting::get('mail_tpl:temporary_password'));
    }
}
