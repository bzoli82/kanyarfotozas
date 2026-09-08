<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function paidOrderFor(Media $media, int $price): Order
    {
        $order = Order::factory()->paid()->create(['total_cents' => $price]);
        $order->media()->attach($media->id, ['price_cents' => $price]);

        return $order;
    }

    public function test_only_admin_and_superadmin_can_view_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($admin)->get('/admin/stats')->assertOk();
        $this->actingAs($photographer)->get('/admin/stats')->assertForbidden();
    }

    public function test_stats_page_lists_sold_media_and_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['name' => 'Eger Kanyar']);
        $media = Media::factory()->create(['event_id' => $event->id, 'price_cents' => 1500]);
        $this->paidOrderFor($media, 1500);

        $response = $this->actingAs($admin)->get('/admin/stats');

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame(1500, $props['summary']['total_revenue_cents']);
        $this->assertSame(1, $props['summary']['media_sold']);
        $this->assertSame('Eger Kanyar', $props['rows']['data'][0]['event_name']);
    }

    public function test_stats_page_filters_by_photographer(): void
    {
        $admin = User::factory()->admin()->create();
        $photographerA = User::factory()->photographer()->create();
        $photographerB = User::factory()->photographer()->create();

        $mediaA = Media::factory()->create(['photographer_id' => $photographerA->id, 'price_cents' => 1000]);
        $mediaB = Media::factory()->create(['photographer_id' => $photographerB->id, 'price_cents' => 2000]);
        $this->paidOrderFor($mediaA, 1000);
        $this->paidOrderFor($mediaB, 2000);

        $response = $this->actingAs($admin)->get("/admin/stats?photographer_id={$photographerA->id}");

        $rows = $response->viewData('page')['props']['rows']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame(1000, $rows[0]['price_cents']);
    }

    public function test_stats_page_filters_by_media_type(): void
    {
        $admin = User::factory()->admin()->create();
        $photo = Media::factory()->photo()->create(['price_cents' => 1000]);
        $video = Media::factory()->video()->create(['price_cents' => 2000]);
        $this->paidOrderFor($photo, 1000);
        $this->paidOrderFor($video, 2000);

        $response = $this->actingAs($admin)->get('/admin/stats?type=video');

        $rows = $response->viewData('page')['props']['rows']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('video', $rows[0]['media_type']);
    }

    public function test_stats_masks_buyer_email_in_table(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['price_cents' => 1000]);
        $order = $this->paidOrderFor($media, 1000);
        $order->update(['buyer_email' => 'teszt.elek@example.com']);

        $response = $this->actingAs($admin)->get('/admin/stats');

        $email = $response->viewData('page')['props']['rows']['data'][0]['buyer_email_masked'];
        $this->assertStringNotContainsString('elek', $email);
    }

    public function test_csv_export_returns_csv_with_filtered_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['price_cents' => 1234]);
        $this->paidOrderFor($media, 1234);

        $response = $this->actingAs($admin)->get('/admin/stats/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('1234', $content);
        $this->assertStringContainsString('Rendelés dátuma', $content);
    }
}
