<?php

namespace Tests\Feature\Console;

use App\Mail\DownloadReminderMail;
use App\Mail\MonthlyPhotographerReportMail;
use App\Mail\WeeklyPhotographerReportMail;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function paidOrderExpiringIn(int $hours): Order
    {
        $order = Order::factory()->paid()->create();
        $order->issueDownloadToken();
        $order->forceFill(['token_expires_at' => now()->addHours($hours)])->save();

        return $order->fresh();
    }

    public function test_download_reminder_is_sent_once_for_orders_expiring_within_48h(): void
    {
        Mail::fake();

        $soon = $this->paidOrderExpiringIn(24);
        $notSoon = $this->paidOrderExpiringIn(96);

        $this->artisan('kanyarfotozas:send-download-reminders')->assertSuccessful();

        Mail::assertQueued(DownloadReminderMail::class, 1);
        Mail::assertQueued(DownloadReminderMail::class, fn ($m) => $m->order->id === $soon->id);
        $this->assertNotNull($soon->fresh()->reminder_sent_at);
        $this->assertNull($notSoon->fresh()->reminder_sent_at);

        // masodik futas nem kuld ujra
        Mail::fake();
        $this->artisan('kanyarfotozas:send-download-reminders')->assertSuccessful();
        Mail::assertNothingQueued();
    }

    public function test_download_reminder_skips_exhausted_and_already_expired_tokens(): void
    {
        Mail::fake();

        $exhausted = $this->paidOrderExpiringIn(10);
        $exhausted->forceFill(['download_token_uses' => Order::TOKEN_MAX_USES])->save();

        $expired = $this->paidOrderExpiringIn(-5);

        $this->artisan('kanyarfotozas:send-download-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_weekly_report_only_goes_to_opted_in_active_photographers(): void
    {
        Mail::fake();

        $optedIn = User::factory()->photographer()->create(['report_weekly' => true, 'is_active' => true]);
        User::factory()->photographer()->create(['report_weekly' => false]);
        User::factory()->photographer()->create(['report_weekly' => true, 'is_active' => false]);

        $media = Media::factory()->create(['photographer_id' => $optedIn->id, 'price_cents' => 2000]);
        $order = Order::factory()->paid()->create(['created_at' => now()->subDays(3)]);
        $order->media()->attach($media->id, ['price_cents' => 2000]);

        $this->artisan('kanyarfotozas:send-weekly-photographer-reports')->assertSuccessful();

        Mail::assertQueued(WeeklyPhotographerReportMail::class, 1);
        Mail::assertQueued(WeeklyPhotographerReportMail::class, fn ($m) => $m->photographer->id === $optedIn->id && $m->report['revenue_cents'] === 2000);
    }

    public function test_monthly_report_attaches_a_csv(): void
    {
        Mail::fake();

        $photographer = User::factory()->photographer()->create(['report_monthly' => true]);
        $media = Media::factory()->create(['photographer_id' => $photographer->id, 'price_cents' => 1500]);
        $order = Order::factory()->paid()->create(['created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2)]);
        $order->media()->attach($media->id, ['price_cents' => 1500]);

        $this->artisan('kanyarfotozas:send-monthly-photographer-reports')->assertSuccessful();

        Mail::assertQueued(MonthlyPhotographerReportMail::class, function ($mail) {
            $attachments = $mail->attachments();

            return count($attachments) === 1 && str_contains($mail->csv, '1500');
        });
    }
}
