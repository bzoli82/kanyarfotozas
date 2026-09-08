<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessImageMedia;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardStatsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DashboardExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_exposes_the_new_epic18_panels(): void
    {
        $admin = User::factory()->admin()->create();

        $props = $this->actingAs($admin)->get('/admin/dashboard')->viewData('page')['props'];

        foreach (['alerts', 'funnel', 'mediaHealth', 'forecast', 'photographerComparison'] as $key) {
            $this->assertArrayHasKey($key, $props);
        }
    }

    public function test_conversion_funnel_counts_each_stage(): void
    {
        // 3 rendeles elindult, 2 fizetes elindult, 1 kifizetve, abbol 0 letoltve
        Order::factory()->count(2)->create();
        Order::factory()->create(['payment_provider_reference' => 'cs_test_x']);
        $paid = Order::factory()->paid()->create();
        $paid->forceFill(['download_token_uses' => 0])->save();

        $funnel = app(DashboardStatsService::class)->conversionFunnel();
        $stages = collect($funnel['stages'])->keyBy('key');

        $this->assertSame(4, $stages['cart']['count']);
        $this->assertSame(2, $stages['checkout']['count']);
        $this->assertSame(1, $stages['paid']['count']);
        $this->assertSame(0, $stages['downloaded']['count']);
        $this->assertSame(100.0, $stages['cart']['pct_of_start']);
    }

    public function test_media_health_reports_problem_counts(): void
    {
        Media::factory()->count(2)->create(['status' => Media::STATUS_FAILED]);
        Media::factory()->create(['status' => Media::STATUS_PROCESSING, 'created_at' => now()->subHours(4)]);
        Media::factory()->video()->create(['status' => Media::STATUS_READY, 'preview_sprite_s3_key' => null]);

        $health = app(DashboardStatsService::class)->mediaHealth();

        $this->assertSame(2, $health['failed']);
        $this->assertSame(1, $health['stuck_processing']);
        $this->assertSame(1, $health['videos_missing_sprite']);
        $this->assertArrayHasKey('enabled', $health['plate_recognition']);
        $this->assertNotEmpty($health['samples']);
    }

    public function test_revenue_forecast_is_at_least_the_actual_so_far(): void
    {
        Order::factory()->paid()->create(['total_cents' => 5000, 'created_at' => now()->subDays(2)]);
        Order::factory()->paid()->create(['total_cents' => 3000, 'created_at' => now()]);

        $forecast = app(DashboardStatsService::class)->revenueForecast();

        $this->assertGreaterThanOrEqual($forecast['month']['actual_cents'], $forecast['month']['forecast_cents']);
        $this->assertGreaterThanOrEqual($forecast['week']['actual_cents'], $forecast['week']['forecast_cents']);
        $this->assertIsInt($forecast['daily_slope_cents']);
    }

    public function test_reprocess_media_resets_status_and_dispatches_a_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $media = Media::factory()->photo()->create(['status' => Media::STATUS_FAILED]);

        $this->actingAs($admin)
            ->post("/admin/dashboard/media/{$media->id}/reprocess")
            ->assertRedirect();

        $this->assertSame(Media::STATUS_PROCESSING, $media->fresh()->status);
        Queue::assertPushed(ProcessImageMedia::class);
    }

    public function test_reprocess_rejects_a_ready_media(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);

        $this->actingAs($admin)
            ->post("/admin/dashboard/media/{$media->id}/reprocess")
            ->assertStatus(422);
    }
}
