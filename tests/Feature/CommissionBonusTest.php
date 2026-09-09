<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Order;
use App\Models\PhotographerEarning;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CommissionBonus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommissionBonusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    private function setTiers(array $tiers): void
    {
        app(CommissionBonus::class)->update($tiers);
    }

    public function test_disabled_without_tiers(): void
    {
        $this->assertFalse(app(CommissionBonus::class)->enabled());
        $this->assertSame(70, app(CommissionBonus::class)->effectiveShare('x', 70));
    }

    public function test_invalid_tiers_are_filtered_and_sorted(): void
    {
        $this->setTiers([
            ['min_sales' => 50, 'bonus_percent' => 10],
            ['min_sales' => 1, 'bonus_percent' => 3],   // min < 2 → out
            ['min_sales' => 20, 'bonus_percent' => 99],  // bonus > 25 → out
            ['min_sales' => 20, 'bonus_percent' => 5],
        ]);

        $this->assertSame(
            [['min_sales' => 20, 'bonus_percent' => 5], ['min_sales' => 50, 'bonus_percent' => 10]],
            app(CommissionBonus::class)->tiers(),
        );
    }

    public function test_effective_share_climbs_with_the_month_sale_count(): void
    {
        $this->setTiers([['min_sales' => 20, 'bonus_percent' => 5], ['min_sales' => 50, 'bonus_percent' => 10]]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);

        $bonus = app(CommissionBonus::class);

        // 0 eddigi eladás → az 1. eladás alap kulcson
        $this->assertSame(70, $bonus->effectiveShare($photographer->id, 70));

        PhotographerEarning::factory()->count(19)->create(['photographer_id' => $photographer->id]);
        // 19 eddigi → a 20. eladás már bónuszos
        $this->assertSame(75, $bonus->effectiveShare($photographer->id, 70));

        PhotographerEarning::factory()->count(30)->create(['photographer_id' => $photographer->id]);
        // 49 eddigi → az 50. eladás a felső sávban
        $this->assertSame(80, $bonus->effectiveShare($photographer->id, 70));
    }

    public function test_share_is_capped_at_95(): void
    {
        $this->setTiers([['min_sales' => 2, 'bonus_percent' => 25]]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 90]);
        PhotographerEarning::factory()->count(5)->create(['photographer_id' => $photographer->id]);

        $this->assertSame(95, app(CommissionBonus::class)->effectiveShare($photographer->id, 90));
    }

    public function test_only_this_month_counts(): void
    {
        $this->setTiers([['min_sales' => 3, 'bonus_percent' => 5]]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);

        PhotographerEarning::factory()->count(10)->create([
            'photographer_id' => $photographer->id,
            'earned_at' => now()->subMonth(),
        ]);

        // A múlt havi eladások nem számítanak → az 1. e havi eladás alap kulcson
        $this->assertSame(70, app(CommissionBonus::class)->effectiveShare($photographer->id, 70));
    }

    public function test_markpaid_snapshots_the_bonused_share_into_the_earning(): void
    {
        $this->setTiers([['min_sales' => 20, 'bonus_percent' => 5]]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        PhotographerEarning::factory()->count(19)->create(['photographer_id' => $photographer->id]);

        $media = Media::factory()->create(['photographer_id' => $photographer->id, 'status' => Media::STATUS_READY, 'price_cents' => 2000]);
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING, 'total_cents' => 2000]);
        $order->media()->attach($media->id, ['price_cents' => 2000]);

        app(CheckoutService::class)->markPaid($order->fresh());

        $earning = PhotographerEarning::query()
            ->where('photographer_id', $photographer->id)
            ->where('media_id', $media->id)
            ->firstOrFail();

        $this->assertSame(75, (int) $earning->share_percent);
        $this->assertSame(1500, (int) $earning->amount_cents);
    }

    public function test_progress_for_reports_the_next_tier(): void
    {
        $this->setTiers([['min_sales' => 20, 'bonus_percent' => 5]]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        PhotographerEarning::factory()->count(18)->create(['photographer_id' => $photographer->id]);

        $progress = app(CommissionBonus::class)->progressFor($photographer);

        $this->assertTrue($progress['enabled']);
        $this->assertSame(18, $progress['sales_this_month']);
        $this->assertSame(70, $progress['current_percent']);
        $this->assertSame(2, $progress['next']['needed']);
    }

    public function test_superadmin_can_save_bonus_tiers(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())->put('/admin/settings/pricing', [
            'base_price' => 1490,
            'tiers' => [],
            'commission_bonus_tiers' => [['min_sales' => 25, 'bonus_percent' => 8]],
        ])->assertRedirect();

        $this->assertSame([['min_sales' => 25, 'bonus_percent' => 8]], app(CommissionBonus::class)->tiers());
    }
}
