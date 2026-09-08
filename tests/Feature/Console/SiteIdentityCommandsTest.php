<?php

namespace Tests\Feature\Console;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteIdentity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteIdentityCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_apply_identity_rewrites_platform_emails_and_domain(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@kanyarfoto.hu']);
        $shooter = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'email' => 'anna@gmail.com']);

        $this->artisan('kanyarfotozas:apply-identity', ['domain' => 'kanyarfotozas.hu', '--from' => 'kanyarfoto.hu'])
            ->assertSuccessful();

        $this->assertSame('superadmin@kanyarfotozas.hu', $sa->fresh()->email);
        $this->assertSame('anna@gmail.com', $shooter->fresh()->email); // külső e-mail nem változik
        $this->assertSame('kanyarfotozas.hu', SiteSetting::get('site_domain'));
    }

    public function test_apply_identity_dry_run_changes_nothing(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@kanyarfoto.hu']);

        $this->artisan('kanyarfotozas:apply-identity', ['domain' => 'kanyarfotozas.hu', '--from' => 'kanyarfoto.hu', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('superadmin@kanyarfoto.hu', $sa->fresh()->email);
        $this->assertNull(SiteSetting::get('site_domain'));
    }

    public function test_apply_identity_rejects_bad_domain(): void
    {
        $this->artisan('kanyarfotozas:apply-identity', ['domain' => 'not a domain'])->assertFailed();
    }

    public function test_audit_identity_flags_db_traces(): void
    {
        User::factory()->create(['email' => 'x@kanyarfoto.hu']);
        SiteSetting::set('mail_from_address', 'noreply@kanyarfoto.hu');

        $this->artisan('kanyarfotozas:audit-identity')
            ->expectsOutputToContain('users.email: x@kanyarfoto.hu')
            ->assertFailed();
    }

    public function test_service_plan_lists_email_and_setting_changes_without_writing(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@kanyarfoto.hu']);
        User::factory()->create(['email' => 'anna@gmail.com']);
        SiteSetting::set('platform_name', 'KanyarFoto.hu');

        $plan = app(SiteIdentity::class)->plan('kanyarfotozas.hu', 'kanyarfoto.hu');

        $this->assertSame('kanyarfoto.hu', $plan['from']);
        $this->assertSame('kanyarfotozas.hu', $plan['to']);
        $this->assertCount(1, $plan['emails']); // csak a superadmin (a gmail nem)
        $this->assertSame('superadmin@kanyarfotozas.hu', $plan['emails'][0]['new']);
        $this->assertContains('platform_name', array_column($plan['settings'], 'key'));

        // Nem írt semmit.
        $this->assertSame('superadmin@kanyarfoto.hu', $sa->fresh()->email);
    }

    public function test_plan_for_the_same_domain_is_a_noop_without_code_hits(): void
    {
        $plan = app(SiteIdentity::class)->plan('kanyarfotozas.hu', 'kanyarfotozas.hu');

        $this->assertTrue($plan['noop']);
        $this->assertSame([], $plan['emails']);
        $this->assertSame([], $plan['code_hits']);
        $this->assertFalse($plan['manual']['needs_db_rename']);
    }

    public function test_service_apply_updates_multiple_email_tables(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@kanyarfoto.hu']);
        ContactMessage::factory()->create(['email' => 'fan@kanyarfoto.hu']);
        ContactMessage::factory()->create(['email' => 'kulso@gmail.com']);

        $result = app(SiteIdentity::class)->apply('kanyarfotozas.hu', 'kanyarfoto.hu');

        $this->assertSame(2, $result['emails_updated']); // superadmin + fan; a gmail nem
        $this->assertDatabaseHas('users', ['email' => 'superadmin@kanyarfotozas.hu']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'fan@kanyarfotozas.hu']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'kulso@gmail.com']);
    }
}
