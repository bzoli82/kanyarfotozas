<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Order;
use App\Models\PhotographerEarning;
use App\Models\PhotographerPayout;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PhotographerPayoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhotographerPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    private function paidOrderForPhotographer(User $photographer, int $price = 3000, string $status = Order::STATUS_PENDING): Order
    {
        $media = Media::factory()->create([
            'photographer_id' => $photographer->id,
            'status' => Media::STATUS_READY,
            'price_cents' => $price,
        ]);

        $order = Order::factory()->create(['payment_status' => $status, 'total_cents' => $price]);
        $order->media()->attach($media->id, ['price_cents' => $price]);

        return $order->fresh();
    }

    public function test_markpaid_records_a_photographer_earning_with_snapshotted_share(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 60]);
        $order = $this->paidOrderForPhotographer($photographer, 5000);

        app(CheckoutService::class)->markPaid($order);

        $earning = PhotographerEarning::query()->where('photographer_id', $photographer->id)->firstOrFail();
        $this->assertSame(5000, $earning->gross_cents);
        $this->assertSame(60, $earning->share_percent);
        $this->assertSame(3000, $earning->amount_cents);
        $this->assertSame(PhotographerEarning::STATUS_PENDING, $earning->status);

        // A jutalek % kesobbi valtozasa nem irja at a mar konyvelt tetelt.
        $photographer->update(['revenue_share_percent' => 90]);
        app(PhotographerPayoutService::class)->recordForOrder($order->fresh());
        $this->assertSame(1, PhotographerEarning::query()->count());
        $this->assertSame(3000, $earning->fresh()->amount_cents);
    }

    public function test_full_refund_reverses_pending_earnings(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        $order = $this->paidOrderForPhotographer($photographer, 4000);
        app(CheckoutService::class)->markPaid($order);

        app(PhotographerPayoutService::class)->reverseForOrder($order->fresh());

        $earning = PhotographerEarning::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(PhotographerEarning::STATUS_REVERSED, $earning->status);
        $this->assertNotNull($earning->reversed_at);

        $out = app(PhotographerPayoutService::class)->outstanding($photographer->fresh());
        $this->assertSame(0, $out['amount_cents']);
    }

    public function test_create_draft_bundles_pending_earnings_and_mark_paid_settles_them(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 50]);
        app(CheckoutService::class)->markPaid($this->paidOrderForPhotographer($photographer, 2000));
        app(CheckoutService::class)->markPaid($this->paidOrderForPhotographer($photographer, 6000));

        $service = app(PhotographerPayoutService::class);
        $payout = $service->createDraft($photographer->fresh());

        $this->assertSame(PhotographerPayout::STATUS_DRAFT, $payout->status);
        $this->assertSame(4000, $payout->amount_cents); // (2000 + 6000) * 50%
        $this->assertSame(2, $payout->media_count);
        $this->assertStringContainsString('-KIF-', $payout->payout_number);
        $this->assertSame(0, $service->outstanding($photographer->fresh())['amount_cents']);

        $service->markPaid($payout, ['method' => 'wise', 'reference' => 'W-123']);
        $payout->refresh();
        $this->assertTrue($payout->isPaid());
        $this->assertSame('wise', $payout->method);
        $this->assertSame(2, PhotographerEarning::query()->where('status', PhotographerEarning::STATUS_PAID)->count());
    }

    public function test_delete_draft_returns_earnings_to_the_open_balance(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        app(CheckoutService::class)->markPaid($this->paidOrderForPhotographer($photographer, 1000));

        $service = app(PhotographerPayoutService::class);
        $payout = $service->createDraft($photographer->fresh());
        $service->deleteDraft($payout);

        $this->assertDatabaseMissing('photographer_payouts', ['id' => $payout->id]);
        $this->assertSame(700, $service->outstanding($photographer->fresh())['amount_cents']);
    }

    public function test_create_draft_without_pending_earnings_fails(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]);

        $this->expectException(ValidationException::class);
        app(PhotographerPayoutService::class)->createDraft($photographer);
    }

    public function test_superadmin_can_create_and_pay_a_payout_over_http(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 40]);
        app(CheckoutService::class)->markPaid($this->paidOrderForPhotographer($photographer, 5000));

        $this->actingAs($superadmin)
            ->get("/admin/photographers/{$photographer->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payout.outstanding.amount_cents', 2000));

        $this->actingAs($superadmin)
            ->post("/admin/photographers/{$photographer->id}/payout", ['method' => 'wise'])
            ->assertRedirect();

        $payout = PhotographerPayout::query()->where('photographer_id', $photographer->id)->firstOrFail();
        $this->assertSame(2000, $payout->amount_cents);

        $this->actingAs($superadmin)
            ->post("/admin/payouts/{$payout->id}/paid", ['method' => 'wise', 'reference' => 'X'])
            ->assertRedirect();

        $this->assertTrue($payout->fresh()->isPaid());
    }

    public function test_photographer_sees_the_earnings_panel_on_the_dashboard(): void
    {
        $photographer = User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        app(CheckoutService::class)->markPaid($this->paidOrderForPhotographer($photographer, 3000));

        $this->actingAs($photographer)
            ->get('/photographer/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('earnings.outstanding_cents', 2100)
                ->where('earnings.outstanding_count', 1));
    }

    public function test_payout_draft_cannot_be_created_for_a_non_photographer(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($superadmin)
            ->post("/admin/photographers/{$admin->id}/payout", ['method' => 'wise'])
            ->assertNotFound();

        $this->actingAs($superadmin)
            ->get("/admin/photographers/{$admin->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payout', null));
    }
}
