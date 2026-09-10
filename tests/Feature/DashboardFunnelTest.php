<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventView;
use App\Models\Media;
use App\Models\Order;
use App\Services\DashboardStatsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_visiting_a_gallery_records_one_view_per_session(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        $this->get("/events/{$event->slug}")->assertOk();
        $this->get("/events/{$event->slug}")->assertOk();

        $this->assertSame(1, (int) EventView::query()->where('event_id', $event->id)->sum('count'));
    }

    public function test_funnel_includes_gallery_stage_when_views_exist(): void
    {
        EventView::query()->create([
            'event_id' => Event::factory()->create()->id,
            'viewed_on' => now()->toDateString(),
            'count' => 40,
        ]);

        $funnel = app(DashboardStatsService::class)->conversionFunnel();

        $this->assertSame('gallery', $funnel['stages'][0]['key']);
        $this->assertSame(40, $funnel['stages'][0]['count']);
    }

    public function test_funnel_omits_gallery_stage_without_view_data(): void
    {
        $funnel = app(DashboardStatsService::class)->conversionFunnel();

        $this->assertSame('cart', $funnel['stages'][0]['key']);
    }

    public function test_event_performance_breaks_the_funnel_down_per_event(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        $media = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        EventView::query()->create(['event_id' => $event->id, 'viewed_on' => now()->toDateString(), 'count' => 20]);

        $order = Order::factory()->paid()->create(['created_at' => now()]);
        $order->media()->attach($media->id, ['price_cents' => 1490]);

        // Egy másik live esemény forgalom nélkül — nem jelenik meg.
        Event::factory()->create(['status' => Event::STATUS_LIVE]);

        $rows = app(DashboardStatsService::class)->eventPerformance();

        $this->assertCount(1, $rows);
        $this->assertSame($event->id, $rows[0]['id']);
        $this->assertSame(20, $rows[0]['views']);
        $this->assertSame(1, $rows[0]['orders']);
        $this->assertSame(1, $rows[0]['paid_orders']);
        $this->assertSame(1490, $rows[0]['revenue_cents']);
        $this->assertSame(5.0, $rows[0]['conversion']);
    }
}
