<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderRefundedMail;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;
use Stripe\Refund;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function paidOrder(string $provider = 'stripe', int $total = 3000): Order
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => $total]);
        $order = Order::factory()->paid()->create([
            'total_cents' => $total,
            'payment_provider' => $provider,
            'payment_provider_reference' => 'ref_'.$provider,
            'payment_provider_order_ref' => 'KAN-1-ABC',
        ]);
        $order->media()->attach($media->id, ['price_cents' => $total]);
        $order->issueDownloadToken();

        return $order->fresh();
    }

    public function test_orders_get_a_human_readable_number_and_are_searchable_by_it(): void
    {
        $order = Order::factory()->create(['buyer_email' => 'a@b.hu']);
        $order->refresh();

        $this->assertMatchesRegularExpression('/^[A-Z]{2,3}-\d{4}-\d{6}$/', $order->order_number);

        Order::factory()->create(['buyer_email' => 'other@x.hu']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?q='.$order->order_number)->assertOk();
        $rows = $response->viewData('page')['props']['orders']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame($order->order_number, $rows[0]['order_number']);
    }

    public function test_show_includes_a_status_timeline(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING]);
        app(CheckoutService::class)->markPaid($order);

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}")->assertOk();
        $descriptions = collect($response->viewData('page')['props']['order']['timeline'])->pluck('description');

        $this->assertTrue($descriptions->contains(fn ($d) => str_contains($d, 'Rendelés létrehozva')));
        $this->assertTrue($descriptions->contains(fn ($d) => str_contains($d, 'Fizetés beérkezett')));
    }

    public function test_index_is_admin_only_and_filters(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_PHOTOGRAPHER]))->get('/admin/orders')->assertForbidden();

        $this->paidOrder();
        Order::factory()->create(['payment_status' => Order::STATUS_PENDING, 'buyer_email' => 'pending@example.com']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?status=paid')->assertOk();
        $rows = $response->viewData('page')['props']['orders']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('paid', $rows[0]['payment_status']);
    }

    public function test_show_returns_order_details(): void
    {
        $order = $this->paidOrder();

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}")->assertOk();
        $props = $response->viewData('page')['props']['order'];
        $this->assertSame($order->buyer_email, $props['buyer_email']);
        $this->assertTrue($props['is_refundable']);
        $this->assertCount(1, $props['items']);
    }

    public function test_stripe_full_refund_marks_order_refunded_and_expires_token(): void
    {
        Mail::fake();
        $order = $this->paidOrder('stripe', 3000);

        $this->mock(StripeCheckoutGateway::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')->once()
                ->andReturn(Session::constructFrom(['id' => 'ref_stripe', 'payment_intent' => 'pi_123']));
            $mock->shouldReceive('createRefund')->once()->with('pi_123', 3000)
                ->andReturn(Refund::constructFrom(['id' => 're_abc']));
        });

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/refund")->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->isRefunded());
        $this->assertSame(3000, $order->refunded_cents);
        $this->assertSame('re_abc', $order->refund_reference);
        $this->assertTrue($order->token_expires_at->isPast());
        Mail::assertQueued(OrderRefundedMail::class);
    }

    public function test_partial_refund_keeps_order_paid(): void
    {
        Mail::fake();
        $order = $this->paidOrder('stripe', 3000);

        $this->mock(StripeCheckoutGateway::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')->andReturn(Session::constructFrom(['id' => 'x', 'payment_intent' => 'pi_1']));
            $mock->shouldReceive('createRefund')->with('pi_1', 1000)->andReturn(Refund::constructFrom(['id' => 're_p']));
        });

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/refund", ['amount_cents' => 1000])->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(1000, $order->refunded_cents);
        $this->assertTrue($order->isRefundable());
    }

    public function test_simplepay_refund_calls_the_provider(): void
    {
        Mail::fake();
        config(['services.simplepay.merchant' => 'M', 'services.simplepay.secret_key' => 'K']);
        Http::fake(['*/payment/v2/refund' => Http::response(['transactionId' => 555], 200)]);

        $order = $this->paidOrder('simplepay', 2000);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/refund")->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/payment/v2/refund') && $r->hasHeader('Signature'));
        $this->assertTrue($order->fresh()->isRefunded());
    }

    public function test_refund_of_non_refundable_order_shows_error(): void
    {
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING, 'total_cents' => 1000]);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/refund")->assertSessionHas('error');
        $this->assertFalse($order->fresh()->isRefunded());
    }

    public function test_resend_email(): void
    {
        Mail::fake();
        $order = $this->paidOrder();

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/resend")->assertRedirect();
        Mail::assertQueued(OrderConfirmationMail::class);
    }
}
