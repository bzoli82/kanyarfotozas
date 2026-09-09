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
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@oldbrand.hu']);
        $shooter = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'email' => 'anna@gmail.com']);

        $this->artisan('roadsidephoto:apply-identity', ['domain' => 'roadsidephoto.eu', '--from' => 'oldbrand.hu'])
            ->assertSuccessful();

        $this->assertSame('superadmin@roadsidephoto.eu', $sa->fresh()->email);
        $this->assertSame('anna@gmail.com', $shooter->fresh()->email); // külső e-mail nem változik
        $this->assertSame('roadsidephoto.eu', SiteSetting::get('site_domain'));
    }

    public function test_apply_identity_dry_run_changes_nothing(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@oldbrand.hu']);

        $this->artisan('roadsidephoto:apply-identity', ['domain' => 'roadsidephoto.eu', '--from' => 'oldbrand.hu', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('superadmin@oldbrand.hu', $sa->fresh()->email);
        $this->assertNull(SiteSetting::get('site_domain'));
    }

    public function test_apply_identity_rejects_bad_domain(): void
    {
        $this->artisan('roadsidephoto:apply-identity', ['domain' => 'not a domain'])->assertFailed();
    }

    public function test_audit_identity_flags_db_traces(): void
    {
        User::factory()->create(['email' => 'x@oldbrand.hu']);
        SiteSetting::set('mail_from_address', 'noreply@oldbrand.hu');

        $this->artisan('roadsidephoto:audit-identity', ['--token' => 'oldbrand'])
            ->expectsOutputToContain('users.email: x@oldbrand.hu')
            ->assertFailed();
    }

    public function test_service_plan_lists_email_and_setting_changes_without_writing(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@oldbrand.hu']);
        User::factory()->create(['email' => 'anna@gmail.com']);
        SiteSetting::set('platform_name', 'OldBrand.hu');

        $plan = app(SiteIdentity::class)->plan('roadsidephoto.eu', 'oldbrand.hu');

        $this->assertSame('oldbrand.hu', $plan['from']);
        $this->assertSame('roadsidephoto.eu', $plan['to']);
        $this->assertCount(1, $plan['emails']); // csak a superadmin (a gmail nem)
        $this->assertSame('superadmin@roadsidephoto.eu', $plan['emails'][0]['new']);
        $this->assertContains('platform_name', array_column($plan['settings'], 'key'));

        // Nem írt semmit.
        $this->assertSame('superadmin@oldbrand.hu', $sa->fresh()->email);
    }

    public function test_plan_for_the_same_domain_is_a_noop_without_code_hits(): void
    {
        $plan = app(SiteIdentity::class)->plan('roadsidephoto.eu', 'roadsidephoto.eu');

        $this->assertTrue($plan['noop']);
        $this->assertSame([], $plan['emails']);
        $this->assertSame([], $plan['code_hits']);
        $this->assertFalse($plan['manual']['needs_db_rename']);
    }

    public function test_service_apply_updates_multiple_email_tables(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'superadmin@oldbrand.hu']);
        ContactMessage::factory()->create(['email' => 'fan@oldbrand.hu']);
        ContactMessage::factory()->create(['email' => 'kulso@gmail.com']);

        $result = app(SiteIdentity::class)->apply('roadsidephoto.eu', 'oldbrand.hu');

        $this->assertSame(2, $result['emails_updated']); // superadmin + fan; a gmail nem
        $this->assertDatabaseHas('users', ['email' => 'superadmin@roadsidephoto.eu']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'fan@roadsidephoto.eu']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'kulso@gmail.com']);
    }
}
