<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\PeriodicStatsExport;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodicStatsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_monthly_rollup_buckets_revenue_and_media_by_month(): void
    {
        $august = Carbon::create(2026, 8, 15, 12);
        $september = Carbon::create(2026, 9, 10, 12);

        $photo = Media::factory()->create(['type' => Media::TYPE_PHOTO, 'created_at' => $august]);
        $video = Media::factory()->video()->create(['created_at' => $september]);

        $augOrder = Order::factory()->paid()->create(['total_cents' => 5000, 'created_at' => $august]);
        $augOrder->media()->attach($photo->id, ['price_cents' => 5000]);

        $sepOrder = Order::factory()->paid()->create(['total_cents' => 3000, 'created_at' => $september]);
        $sepOrder->media()->attach($video->id, ['price_cents' => 3000]);

        $rows = app(PeriodicStatsExport::class)->rollup(
            Carbon::create(2026, 8, 1),
            Carbon::create(2026, 9, 30),
        );

        $this->assertCount(2, $rows);

        $aug = collect($rows)->firstWhere('period', '2026-08');
        $this->assertSame(5000, $aug['revenue_huf']);
        $this->assertSame(1, $aug['orders_paid']);
        $this->assertSame(1, $aug['media_sold_photo']);
        $this->assertSame(0, $aug['media_sold_video']);

        $sep = collect($rows)->firstWhere('period', '2026-09');
        $this->assertSame(3000, $sep['revenue_huf']);
        $this->assertSame(1, $sep['media_sold_video']);
    }

    public function test_export_endpoint_returns_csv_with_a_header_row_for_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dashboard/stats/export?from=2026-07&to=2026-09&granularity=month');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('Időszak', $body);
        $this->assertStringContainsString('Eladott média', $body);
        // 3 honap + fejlec
        $this->assertSame(4, substr_count(trim($body), "\n") + 1);
    }

    public function test_weekly_granularity_produces_weekly_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $body = $this->actingAs($admin)
            ->get('/admin/dashboard/stats/export?from=2026-09-01&to=2026-09-21&granularity=week')
            ->streamedContent();

        $this->assertStringContainsString(' – ', $body); // heti sorok "kezd – veg" formatumban
    }

    public function test_export_is_forbidden_for_photographers(): void
    {
        $this->actingAs(User::factory()->photographer()->create())
            ->get('/admin/dashboard/stats/export')
            ->assertForbidden();
    }
}
