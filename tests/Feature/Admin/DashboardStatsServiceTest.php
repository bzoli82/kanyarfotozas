<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventSubscription;
use App\Models\FailedLoginAttempt;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardStatsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function paidOrderFor(Media $media, int $price): Order
    {
        $order = Order::factory()->paid()->create(['total_cents' => $price, 'created_at' => now()]);
        $order->media()->attach($media->id, ['price_cents' => $price]);

        return $order;
    }

    public function test_kpis_sum_revenue_and_media_sold_for_paid_orders_only(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $this->paidOrderFor($media, 1000);
        Order::factory()->create(['total_cents' => 5000]); // pending — nem szamit bele

        $kpis = app(DashboardStatsService::class)->kpis();

        $this->assertSame(1000, $kpis['revenue']['today']);
        $this->assertSame(1, $kpis['media_sold']['today']);
        $this->assertSame(1, $kpis['active_orders_today']);
    }

    public function test_kpis_count_failed_media_and_photographer_active_state(): void
    {
        $activePhotographer = User::factory()->photographer()->create(['is_active' => true]);
        $inactivePhotographer = User::factory()->photographer()->create(['is_active' => false]);
        Media::factory()->create(['status' => Media::STATUS_FAILED, 'photographer_id' => $activePhotographer->id]);

        $kpis = app(DashboardStatsService::class)->kpis();

        $this->assertSame(1, $kpis['failed_media_count']);
        $this->assertSame(1, $kpis['photographers']['active']);
        $this->assertSame(1, $kpis['photographers']['inactive']);
    }

    public function test_subscribers_count_reports_distinct_subscriber_emails(): void
    {
        EventSubscription::query()->create(['email' => 'fan@example.com', 'location' => 'Eger']);
        EventSubscription::query()->create(['email' => 'fan@example.com', 'location' => 'Mátraháza']);
        EventSubscription::query()->create(['email' => 'other@example.com', 'location' => 'Eger']);

        $kpis = app(DashboardStatsService::class)->kpis();

        $this->assertSame(2, $kpis['subscribers_count']);
    }

    public function test_active_download_tokens_counts_only_non_expired(): void
    {
        $active = Order::factory()->paid()->create();
        $active->issueDownloadToken();

        $expired = Order::factory()->paid()->create();
        $expired->issueDownloadToken();
        $expired->forceFill(['token_expires_at' => now()->subHour()])->save();

        $kpis = app(DashboardStatsService::class)->kpis();

        $this->assertSame(1, $kpis['active_download_tokens']);
    }

    public function test_revenue_trend_returns_30_days_with_correct_totals(): void
    {
        $media = Media::factory()->create(['price_cents' => 1500]);
        $this->paidOrderFor($media, 1500);

        $trend = app(DashboardStatsService::class)->revenueTrend();

        $this->assertCount(30, $trend['labels']);
        $this->assertCount(30, $trend['data']);
        $this->assertSame(1500, end($trend['data']));
    }

    public function test_media_type_split_counts_sold_photos_and_videos_separately(): void
    {
        $photo = Media::factory()->photo()->create(['price_cents' => 1000]);
        $video = Media::factory()->video()->create(['price_cents' => 2000]);
        $this->paidOrderFor($photo, 1000);
        $this->paidOrderFor($video, 2000);

        $split = app(DashboardStatsService::class)->mediaTypeSplit();

        $this->assertSame(1, $split['photo']);
        $this->assertSame(1, $split['video']);
    }

    public function test_top_events_orders_by_revenue_descending(): void
    {
        $bigEvent = Event::factory()->create(['name' => 'Nagy Esemény']);
        $smallEvent = Event::factory()->create(['name' => 'Kis Esemény']);

        $bigMedia = Media::factory()->create(['event_id' => $bigEvent->id, 'price_cents' => 5000]);
        $smallMedia = Media::factory()->create(['event_id' => $smallEvent->id, 'price_cents' => 500]);

        $this->paidOrderFor($bigMedia, 5000);
        $this->paidOrderFor($smallMedia, 500);

        $top = app(DashboardStatsService::class)->topEvents();

        $this->assertSame('Nagy Esemény', $top['labels'][0]);
        $this->assertSame(5000, $top['data'][0]);
    }

    public function test_latest_orders_masks_buyer_email(): void
    {
        Order::factory()->paid()->create(['buyer_email' => 'janos.kovacs@example.com']);

        $orders = app(DashboardStatsService::class)->latestOrders();

        $this->assertStringNotContainsString('kovacs', $orders[0]['email_masked']);
        $this->assertStringStartsWith('ja', $orders[0]['email_masked']);
        $this->assertStringContainsString('@example.com', $orders[0]['email_masked']);
    }

    public function test_security_alert_is_active_after_more_than_five_failed_logins_in_24h(): void
    {
        for ($i = 0; $i < 6; $i++) {
            FailedLoginAttempt::create(['email' => 'attacker@example.com', 'ip_hash' => 'hash']);
        }

        $security = app(DashboardStatsService::class)->securityAlerts();

        $this->assertTrue($security['active']);
        $this->assertSame(6, $security['failed_logins_24h']);
    }

    public function test_security_alert_ignores_attempts_older_than_24_hours(): void
    {
        $attempt = FailedLoginAttempt::create(['email' => 'x@example.com', 'ip_hash' => 'h']);
        $attempt->forceFill(['created_at' => now()->subDays(2)])->save();

        $security = app(DashboardStatsService::class)->securityAlerts();

        $this->assertFalse($security['active']);
        $this->assertSame(0, $security['failed_logins_24h']);
    }
}
