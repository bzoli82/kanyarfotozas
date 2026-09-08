<?php

namespace Tests\Feature\Public;

use App\Mail\OrderConfirmationMail;
use App\Models\Coupon;
use App\Models\Media;
use App\Models\Order;
use App\Services\CheckoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creates_pending_order_with_snapshot_prices(): void
    {
        $photo = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $video = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2000]);

        $order = app(CheckoutService::class)->createPendingOrder(
            [$photo->id, $video->id],
            'vevo@example.com',
            null,
        );

        $this->assertSame('vevo@example.com', $order->buyer_email);
        $this->assertSame(3000, $order->total_cents);
        $this->assertSame(Order::STATUS_PENDING, $order->payment_status);
        $this->assertCount(2, $order->media);
    }

    public function test_ignores_non_ready_media_when_creating_order(): void
    {
        $ready = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $hidden = Media::factory()->create(['status' => Media::STATUS_HIDDEN, 'price_cents' => 1000]);

        $order = app(CheckoutService::class)->createPendingOrder(
            [$ready->id, $hidden->id],
            'vevo@example.com',
            null,
        );

        $this->assertSame(1000, $order->total_cents);
        $this->assertCount(1, $order->media);
    }

    public function test_throws_when_no_media_is_available(): void
    {
        $hidden = Media::factory()->create(['status' => Media::STATUS_HIDDEN]);

        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->createPendingOrder([$hidden->id], 'vevo@example.com', null);
    }

    public function test_applies_valid_coupon_discount(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $coupon = Coupon::factory()->create(['code' => 'TESZT20', 'discount_percent' => 20]);

        $order = app(CheckoutService::class)->createPendingOrder([$media->id], 'vevo@example.com', 'TESZT20');

        $this->assertSame(200, $order->discount_cents);
        $this->assertSame(800, $order->total_cents);
        $this->assertSame($coupon->id, $order->coupon_id);
    }

    public function test_rejects_invalid_coupon(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);

        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->createPendingOrder([$media->id], 'vevo@example.com', 'NEMLETEZIK');
    }

    public function test_mark_paid_issues_download_token_and_sends_email(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $order = app(CheckoutService::class)->createPendingOrder([$media->id], 'vevo@example.com', null);

        app(CheckoutService::class)->markPaid($order);
        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertNotNull($order->download_token);
        $this->assertTrue($order->token_expires_at->isFuture());
        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id);
    }

    public function test_mark_paid_increments_coupon_usage(): void
    {
        Mail::fake();

        $coupon = Coupon::factory()->create(['discount_percent' => 10, 'used_count' => 0]);
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $order = app(CheckoutService::class)->createPendingOrder([$media->id], 'vevo@example.com', $coupon->code);

        app(CheckoutService::class)->markPaid($order);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_mark_paid_is_idempotent(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $order = app(CheckoutService::class)->createPendingOrder([$media->id], 'vevo@example.com', null);

        app(CheckoutService::class)->markPaid($order);
        $tokenAfterFirst = $order->fresh()->download_token;

        app(CheckoutService::class)->markPaid($order->fresh());

        $this->assertSame($tokenAfterFirst, $order->fresh()->download_token);
        Mail::assertQueued(OrderConfirmationMail::class, 1);
    }
}
