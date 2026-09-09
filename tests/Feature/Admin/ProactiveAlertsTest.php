<?php

namespace Tests\Feature\Admin;

use App\Mail\ProactiveAlertDigestMail;
use App\Models\DismissedAlert;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\ProactiveAlerts;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProactiveAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_failed_media_produces_a_critical_alert(): void
    {
        Media::factory()->count(3)->create(['status' => Media::STATUS_FAILED]);

        $alerts = app(ProactiveAlerts::class)->all();
        $failed = collect($alerts)->firstWhere('key', 'media_failed');

        $this->assertNotNull($failed);
        $this->assertSame(ProactiveAlerts::SEVERITY_CRITICAL, $failed['severity']);
        $this->assertStringContainsString('3', $failed['title']);
    }

    public function test_no_alerts_when_system_is_healthy(): void
    {
        Media::factory()->create(['status' => Media::STATUS_READY, 'created_at' => now()]);

        $keys = collect(app(ProactiveAlerts::class)->all())->pluck('key');

        $this->assertFalse($keys->contains('media_failed'));
        $this->assertFalse($keys->contains('media_stuck'));
        $this->assertFalse($keys->contains('no_recent_uploads'));
    }

    public function test_stuck_processing_and_announced_past_alerts(): void
    {
        Media::factory()->create(['status' => Media::STATUS_PROCESSING, 'created_at' => now()->subHours(5)]);
        Event::factory()->create([
            'name' => 'Régi Kanyar',
            'location' => 'Múltfalu',
            'status' => Event::STATUS_ANNOUNCED,
            'starts_at' => now()->subDays(2),
        ]);

        $keys = collect(app(ProactiveAlerts::class)->all())->pluck('key');

        $this->assertTrue($keys->contains('media_stuck'));
        $this->assertTrue($keys->contains('announced_past'));
    }

    public function test_dismissing_an_alert_hides_it_from_visible_but_not_from_all(): void
    {
        Media::factory()->count(2)->create(['status' => Media::STATUS_FAILED]);
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->post('/admin/dashboard/alerts/dismiss', ['key' => 'media_failed'])
            ->assertRedirect();

        $this->assertDatabaseHas('dismissed_alerts', ['alert_key' => 'media_failed']);

        $service = app(ProactiveAlerts::class);
        $this->assertTrue(collect($service->all())->pluck('key')->contains('media_failed'));
        $this->assertFalse(collect($service->visible())->pluck('key')->contains('media_failed'));
    }

    public function test_dismissal_expires_after_the_configured_window(): void
    {
        Media::factory()->create(['status' => Media::STATUS_FAILED]);
        DismissedAlert::query()->create([
            'alert_key' => 'media_failed',
            'dismissed_until' => now()->subMinute(),
        ]);

        $this->assertTrue(
            collect(app(ProactiveAlerts::class)->visible())->pluck('key')->contains('media_failed')
        );
    }

    public function test_scan_alerts_command_emails_superadmins_about_critical_alerts(): void
    {
        Mail::fake();

        User::factory()->superadmin()->create(['email' => 'boss@example.test']);
        Media::factory()->create(['status' => Media::STATUS_FAILED]);

        $this->artisan('roadsidephoto:scan-alerts')->assertSuccessful();

        Mail::assertQueued(ProactiveAlertDigestMail::class);

        // Masodik futas ugyanazzal a keszlettel — nem kuld ujra.
        Mail::fake();
        $this->artisan('roadsidephoto:scan-alerts')->assertSuccessful();
        Mail::assertNothingQueued();
    }
}
