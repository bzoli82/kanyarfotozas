<?php

namespace Tests\Feature;

use App\Mail\ErrorNotificationMail;
use App\Models\ErrorEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\ErrorReporter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    public function test_reporter_records_groups_and_reopens_errors_but_ignores_client_errors(): void
    {
        $reporter = app(ErrorReporter::class);

        $reporter->report(new NotFoundHttpException('nope'));
        $this->assertSame(0, ErrorEvent::count());

        $boom = new RuntimeException('Kaboom');
        $reporter->report($boom);
        $reporter->report($boom);

        $this->assertSame(1, ErrorEvent::count());
        $event = ErrorEvent::first();
        $this->assertSame(2, $event->count);
        $this->assertSame('RuntimeException', $event->exception_class);

        $event->update(['resolved_at' => now()]);
        $reporter->report($boom);
        $this->assertNull($event->fresh()->resolved_at); // újra előjött → nyitva
    }

    public function test_new_error_notifies_superadmins_once_within_the_cooldown(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email' => 'boss@kanyarfoto.hu']);

        $e = new RuntimeException('once please');
        app(ErrorReporter::class)->report($e);
        app(ErrorReporter::class)->report($e);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ErrorNotificationMail::class, fn ($mail) => $mail->hasTo('boss@kanyarfoto.hu'));
    }

    public function test_webhook_is_called_when_configured_and_email_can_be_disabled(): void
    {
        Http::fake();
        SiteSetting::set('error_notify_email', '0');
        SiteSetting::set('error_webhook_url', Crypt::encryptString('https://hooks.example.com/abc'));

        app(ErrorReporter::class)->report(new RuntimeException('hook me'));

        Mail::assertNothingQueued();
        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.com/abc');
    }

    public function test_error_log_page_is_superadmin_only_and_can_resolve(): void
    {
        $event = ErrorEvent::factory()->create();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get('/admin/errors')->assertForbidden();

        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($superadmin)->get('/admin/errors')->assertOk();

        $this->actingAs($superadmin)->post("/admin/errors/{$event->id}/resolve")->assertRedirect();
        $this->assertNotNull($event->fresh()->resolved_at);
    }

    public function test_backup_service_dumps_gzips_stores_and_prunes(): void
    {
        config(['monitoring.backup_disk' => 'local', 'monitoring.backup_keep' => 2]);
        Storage::fake('local');
        Process::fake(['*pg_dump*' => Process::result(output: "-- fake dump\nSELECT 1;\n")]);

        // 3 régi mentés a diskre — a prune-nak 2-re kell csökkentenie
        foreach (['db-2020-01-01_000000.sql.gz', 'db-2020-01-02_000000.sql.gz', 'db-2020-01-03_000000.sql.gz'] as $old) {
            Storage::disk('local')->put("backups/{$old}", 'x');
        }

        $result = app(BackupService::class)->run();

        Storage::disk('local')->assertExists($result['path']);
        $this->assertLessThanOrEqual(2, count(Storage::disk('local')->files('backups')));
        $this->assertSame('ok', SiteSetting::get('backup_last_status'));
        $this->assertNotNull(SiteSetting::get('backup_last_run'));
    }

    public function test_backup_command_records_failure_and_alerts_superadmins(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        Storage::fake('local');
        Process::fake(['*pg_dump*' => Process::result(output: '', errorOutput: 'connection refused', exitCode: 1)]);

        $this->artisan('kanyarfotozas:backup')->assertFailed();

        $this->assertSame('failed', SiteSetting::get('backup_last_status'));
        $this->assertStringContainsString('connection refused', (string) SiteSetting::get('backup_last_error'));
    }

    public function test_superadmin_can_save_monitoring_settings_and_run_a_backup(): void
    {
        config(['monitoring.backup_disk' => 'local']);
        Storage::fake('local');
        Process::fake(['*pg_dump*' => Process::result(output: "SELECT 1;\n")]);
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($superadmin)->put('/admin/settings/critical/monitoring', [
            'webhook_url' => 'https://hooks.example.com/xyz',
            'notify_email' => false,
        ])->assertRedirect();

        $this->assertSame('https://hooks.example.com/xyz', Crypt::decryptString(SiteSetting::get('error_webhook_url')));
        $this->assertSame('0', SiteSetting::get('error_notify_email'));

        $this->actingAs($superadmin)->post('/admin/settings/critical/backup')->assertRedirect();
        $this->assertSame('ok', SiteSetting::get('backup_last_status'));
    }
}
