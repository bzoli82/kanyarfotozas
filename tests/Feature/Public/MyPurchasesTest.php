<?php

namespace Tests\Feature\Public;

use App\Mail\PurchaseOtpMail;
use App\Models\Media;
use App\Models\Order;
use App\Models\PurchaseOtp;
use App\Services\PurchaseLookup;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyPurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_requesting_an_otp_sends_a_mail_and_stores_only_a_hash(): void
    {
        Mail::fake();

        $this->post('/my-purchases/request-otp', ['email' => 'buyer@example.com'])->assertRedirect();

        Mail::assertQueued(PurchaseOtpMail::class);
        $otp = PurchaseOtp::query()->firstOrFail();
        $this->assertSame(64, strlen($otp->otp_hash));
        $this->assertNotSame('buyer@example.com', $otp->email_hash);
    }

    public function test_rate_limited_after_three_requests_per_hour(): void
    {
        Mail::fake();
        $lookup = app(PurchaseLookup::class);

        foreach (range(1, 3) as $i) {
            $lookup->issueOtp('spammy@example.com');
        }

        $this->post('/my-purchases/request-otp', ['email' => 'spammy@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_verifying_a_correct_otp_lists_paid_orders_for_that_email(): void
    {
        $lookup = app(PurchaseLookup::class);
        $otp = $lookup->issueOtp('buyer@example.com');

        $media = Media::factory()->create(['price_cents' => 1490]);
        $order = Order::factory()->paid()->create(['buyer_email' => 'buyer@example.com', 'total_cents' => 1490]);
        $order->media()->attach($media->id, ['price_cents' => 1490]);

        $this->post('/my-purchases/verify', ['email' => 'buyer@example.com', 'otp' => $otp])
            ->assertRedirect('/my-purchases');

        $props = $this->get('/my-purchases')->viewData('page')['props'];
        $this->assertSame('buyer@example.com', $props['verifiedEmail']);
        $this->assertCount(1, $props['orders']);
        $this->assertSame($order->id, $props['orders'][0]['id']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        app(PurchaseLookup::class)->issueOtp('buyer@example.com');

        $this->post('/my-purchases/verify', ['email' => 'buyer@example.com', 'otp' => '000000'])
            ->assertSessionHasErrors('otp');
    }

    public function test_otp_is_single_use(): void
    {
        $lookup = app(PurchaseLookup::class);
        $otp = $lookup->issueOtp('buyer@example.com');

        $this->assertTrue($lookup->verify('buyer@example.com', $otp));
        $this->assertFalse($lookup->verify('buyer@example.com', $otp));
    }

    public function test_resend_reissues_an_expired_token(): void
    {
        $media = Media::factory()->create();
        $order = Order::factory()->paid()->create(['buyer_email' => 'buyer@example.com']);
        $order->media()->attach($media->id, ['price_cents' => 990]);
        $oldToken = (string) Str::uuid();
        $order->forceFill(['download_token' => $oldToken, 'token_expires_at' => now()->subDay(), 'download_token_uses' => 0])->save();

        $this->withSession(['verified_purchase_email' => 'buyer@example.com'])
            ->post('/my-purchases/resend', ['order_id' => $order->id])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertNotSame($oldToken, $fresh->download_token);
        $this->assertTrue($fresh->token_expires_at->isFuture());
    }
}
