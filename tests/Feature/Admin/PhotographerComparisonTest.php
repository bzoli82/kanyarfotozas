<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\PhotographerComparison;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_rows_aggregate_media_sales_and_conversion_per_photographer(): void
    {
        $photographer = User::factory()->photographer()->create([
            'name' => 'Teszt Fotós',
            'revenue_share_percent' => 60,
        ]);
        $event = Event::factory()->create(['name' => 'Teszt Kanyar', 'location' => 'Tesztváros']);

        $sold = Media::factory()->count(2)->create([
            'photographer_id' => $photographer->id,
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
        ]);
        Media::factory()->count(2)->create([
            'photographer_id' => $photographer->id,
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
        ]);

        $order = Order::factory()->paid()->create();
        foreach ($sold as $media) {
            $order->media()->attach($media->id, ['price_cents' => 1000]);
        }

        $row = collect(app(PhotographerComparison::class)->rows())->firstWhere('id', $photographer->id);

        $this->assertSame(4, $row['media_total']);
        $this->assertSame(4, $row['media_ready']);
        $this->assertSame(2, $row['media_sold']);
        $this->assertSame(2000, $row['revenue_cents']);
        $this->assertSame(1200, $row['photographer_share_cents']);
        $this->assertSame(50.0, $row['conversion_rate']);
        $this->assertSame(1000, $row['avg_price_cents']);
    }

    public function test_export_returns_a_csv_with_a_header_row(): void
    {
        User::factory()->photographer()->create(['name' => 'CSV Fotós']);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dashboard/photographers/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('Fotós', $body);
        $this->assertStringContainsString('Konverzió %', $body);
        $this->assertStringContainsString('CSV Fotós', $body);
    }

    public function test_export_is_forbidden_for_photographers(): void
    {
        $this->actingAs(User::factory()->photographer()->create())
            ->get('/admin/dashboard/photographers/export')
            ->assertForbidden();
    }

    public function test_watchlist_flags_a_photographer_with_inquiries_but_no_sales(): void
    {
        $flagged = User::factory()->photographer()->create(['name' => 'Gyanús Fotós']);
        $healthy = User::factory()->photographer()->create(['name' => 'Rendes Fotós']);

        Media::factory()->count(20)->create(['photographer_id' => $flagged->id, 'status' => Media::STATUS_READY]);
        ContactMessage::factory()->count(4)->forPhotographer($flagged)->create();

        $healthyMedia = Media::factory()->count(20)->create(['photographer_id' => $healthy->id, 'status' => Media::STATUS_READY]);
        $order = Order::factory()->paid()->create();
        foreach ($healthyMedia->take(6) as $m) {
            $order->media()->attach($m->id, ['price_cents' => 1000]);
        }

        $watch = app(PhotographerComparison::class)->watchlist();

        $this->assertSame(['Gyanús Fotós'], collect($watch)->pluck('name')->all());
        $this->assertNotEmpty($watch[0]['flag_reasons']);
    }

    public function test_conversion_watch_prop_is_superadmin_only(): void
    {
        $flagged = User::factory()->photographer()->create();
        Media::factory()->count(20)->create(['photographer_id' => $flagged->id, 'status' => Media::STATUS_READY]);
        ContactMessage::factory()->count(4)->forPhotographer($flagged)->create();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')
            ->assertInertia(fn ($p) => $p->where('conversionWatch', []));

        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/dashboard')
            ->assertInertia(fn ($p) => $p->where('conversionWatch', fn ($w) => count($w) === 1));
    }
}
