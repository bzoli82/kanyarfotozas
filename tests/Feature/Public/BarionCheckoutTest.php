<?php

namespace Tests\Feature\Public;

use App\Mail\OrderConfirmationMail;
use App\Models\Media;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BarionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        config([
            'services.barion.pos_key' => 'test-pos-key',
            'services.barion.payee' => 'shop@example.com',
            'services.barion.sandbox' => true,
        ]);
    }

    public function test_checkout_with_barion_provider_starts_a_payment(): void
    {
        Http::fake([
            'api.test.barion.com/v2/Payment/Start' => Http::response([
                'PaymentId' => 'pay-abc-123',
                'PaymentRequestId' => 'KF-1-ABCDEFGH',
                'Status' => 'Prepared',
                'GatewayUrl' => 'https://secure.test.barion.com/Pay?Id=pay-abc-123',
                'Errors' => [],
            ], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);

        $response = $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'vevo@example.com',
            'terms_accepted' => true,
            'provider' => 'barion',
        ]);

        $response->assertOk()->assertJson(['redirect_url' => 'https://secure.test.barion.com/Pay?Id=pay-abc-123']);

        $order = Order::query()->firstOrFail();
        $this->assertSame('barion', $order->payment_provider);
        $this->assertSame('pay-abc-123', $order->payment_provider_reference);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/Payment/Start')
            && $request['POSKey'] === 'test-pos-key'
            && $request['Transactions'][0]['Payee'] === 'shop@example.com');
    }

    public function test_checkout_returns_friendly_error_when_barion_rejects(): void
    {
        Http::fake([
            'api.test.barion.com/v2/Payment/Start' => Http::response([
                'Errors' => [['ErrorCode' => 'ShopIsBlocked', 'Title' => 'Shop is blocked']],
            ], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);

        $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'vevo@example.com',
            'terms_accepted' => true,
            'provider' => 'barion',
        ])->assertStatus(502);
    }

    public function test_callback_marks_order_paid_when_barion_reports_succeeded(): void
    {
        Mail::fake();

        Http::fake([
            'api.test.barion.com/v2/Payment/GetPaymentState*' => Http::response([
                'PaymentId' => 'pay-777',
                'PaymentRequestId' => 'KF-9-XX',
                'Status' => 'Succeeded',
                'Transactions' => [['TransactionId' => 'txn-1', 'TransactionType' => 'CardProcessed']],
            ], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);
        $order = Order::factory()->create(['payment_provider' => 'barion', 'payment_provider_reference' => 'pay-777']);
        $order->media()->attach($media->id, ['price_cents' => 2500]);

        $this->post('/api/barion/callback', ['paymentId' => 'pay-777'])->assertOk();

        $this->assertTrue($order->fresh()->isPaid());
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_callback_ignores_non_succeeded_state(): void
    {
        Http::fake([
            'api.test.barion.com/v2/Payment/GetPaymentState*' => Http::response(['Status' => 'Canceled'], 200),
        ]);

        $order = Order::factory()->create(['payment_provider' => 'barion', 'payment_provider_reference' => 'pay-x']);

        $this->post('/api/barion/callback', ['paymentId' => 'pay-x'])->assertOk();

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_return_page_shows_success_when_state_is_succeeded(): void
    {
        Mail::fake();

        Http::fake([
            'api.test.barion.com/v2/Payment/GetPaymentState*' => Http::response(['Status' => 'Succeeded'], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);
        $order = Order::factory()->create(['payment_provider' => 'barion', 'payment_provider_reference' => 'pay-ret']);
        $order->media()->attach($media->id, ['price_cents' => 2500]);

        $response = $this->get('/checkout/barion/return?'.http_build_query(['paymentId' => 'pay-ret']));

        $response->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertTrue($response->viewData('page')['props']['paid']);
    }
}
