<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\BulkDiscount;
use App\Services\CheckoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function setTiers(array $tiers): void
    {
        app(BulkDiscount::class)->update($tiers);
    }

    public function test_feature_is_off_without_tiers(): void
    {
        $this->assertFalse(app(BulkDiscount::class)->enabled());
        $this->assertSame([], app(BulkDiscount::class)->tiers());
    }

    public function test_invalid_tiers_are_filtered_and_sorted(): void
    {
        $this->setTiers([
            ['min' => 10, 'percent' => 20],
            ['min' => 1, 'percent' => 5],   // min < 2 → kiesik
            ['min' => 5, 'percent' => 200], // percent > 90 → kiesik
            ['min' => 3, 'percent' => 10],
        ]);

        $this->assertSame(
            [['min' => 3, 'percent' => 10], ['min' => 10, 'percent' => 20]],
            app(BulkDiscount::class)->tiers(),
        );
    }

    public function test_discount_is_calculated_per_event_with_best_tier(): void
    {
        $this->setTiers([['min' => 3, 'percent' => 10], ['min' => 5, 'percent' => 20]]);

        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();

        $items = collect()
            ->merge(collect(range(1, 5))->map(fn () => ['event_id' => $eventA->id, 'price_cents' => 1000]))
            ->merge(collect(range(1, 2))->map(fn () => ['event_id' => $eventB->id, 'price_cents' => 1000]));

        $result = app(BulkDiscount::class)->forItems($items);

        // A esemény: 5 kép * 1000 = 5000, 20% = 1000. B esemény: 2 kép, nincs sáv.
        $this->assertSame(1000, $result['discount_cents']);
        $this->assertCount(1, $result['groups']);
        $this->assertSame(20, $result['groups'][0]['percent']);
    }

    public function test_hint_appears_when_close_to_the_next_tier(): void
    {
        $this->setTiers([['min' => 5, 'percent' => 15]]);
        $event = Event::factory()->create();

        $result = app(BulkDiscount::class)->forItems(
            collect(range(1, 3))->map(fn () => ['event_id' => $event->id, 'price_cents' => 1000]),
        );

        $this->assertSame(0, $result['discount_cents']);
        $this->assertSame([['event_id' => $event->id, 'needed' => 2, 'percent' => 15]], $result['hints']);
    }

    public function test_items_without_an_event_are_ignored(): void
    {
        $this->setTiers([['min' => 2, 'percent' => 10]]);

        $result = app(BulkDiscount::class)->forItems([
            ['event_id' => null, 'price_cents' => 1000],
            ['event_id' => null, 'price_cents' => 1000],
        ]);

        $this->assertSame(0, $result['discount_cents']);
    }

    public function test_checkout_stores_bulk_discount_and_reduces_total(): void
    {
        $this->setTiers([['min' => 3, 'percent' => 10]]);

        $event = Event::factory()->create();
        $media = Media::factory()->count(3)->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'price_cents' => 1000,
        ]);

        $order = app(CheckoutService::class)->createPendingOrder(
            $media->pluck('id')->all(),
            'vevo@example.com',
            null,
        );

        $this->assertSame(300, $order->bulk_discount_cents);
        $this->assertSame(2700, $order->total_cents);
    }

    public function test_coupon_stacks_on_the_bulk_reduced_subtotal(): void
    {
        $this->setTiers([['min' => 3, 'percent' => 10]]);
        Coupon::factory()->create(['code' => 'TESZT20', 'discount_percent' => 20]);

        $event = Event::factory()->create();
        $media = Media::factory()->count(3)->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'price_cents' => 1000,
        ]);

        $order = app(CheckoutService::class)->createPendingOrder(
            $media->pluck('id')->all(),
            'vevo@example.com',
            'TESZT20',
        );

        // 3000 - 300 (bulk) = 2700, ebből 20% = 540 kupon.
        $this->assertSame(300, $order->bulk_discount_cents);
        $this->assertSame(540, $order->discount_cents);
        $this->assertSame(2160, $order->total_cents);
    }

    public function test_api_cart_returns_bulk_discount_payload(): void
    {
        $this->setTiers([['min' => 2, 'percent' => 10]]);

        $event = Event::factory()->create();
        $media = Media::factory()->count(2)->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'price_cents' => 1000,
        ]);

        $this->getJson('/api/cart?'.http_build_query(['ids' => $media->pluck('id')->all()]))
            ->assertOk()
            ->assertJsonPath('bulk_discount.discount_cents', 200);
    }

    public function test_superadmin_can_save_pricing_tiers(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/pricing', [
            'base_price' => 1990,
            'tiers' => [['min' => 4, 'percent' => 15]],
        ])->assertRedirect();

        $this->assertSame([['min' => 4, 'percent' => 15]], app(BulkDiscount::class)->tiers());
    }

    public function test_pricing_page_is_superadmin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/pricing')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/pricing')->assertOk();
    }
}
