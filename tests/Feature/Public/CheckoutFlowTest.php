<?php

namespace Tests\Feature\Public;

use App\Mail\OrderConfirmationMail;
use App\Models\Media;
use App\Models\Order;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_creates_order_and_returns_stripe_redirect_url(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1500]);

        $this->mock(StripeCheckoutGateway::class, function ($mock) {
            $mock->shouldReceive('createSession')
                ->once()
                ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/pay/cs_test_123']));
        });

        $response = $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'vevo@example.com',
            'terms_accepted' => true,
        ]);

        $response->assertOk()->assertJson(['redirect_url' => 'https://checkout.stripe.com/pay/cs_test_123']);

        $order = Order::query()->firstOrFail();
        $this->assertSame('cs_test_123', $order->payment_provider_reference);
        $this->assertSame('stripe', $order->payment_provider);
        $this->assertSame(1500, $order->total_cents);
        $this->assertNotNull($order->terms_accepted_at);
    }

    public function test_checkout_rejects_empty_media_list(): void
    {
        $response = $this->postJson('/checkout', ['media_ids' => [], 'email' => 'vevo@example.com', 'terms_accepted' => true]);

        $response->assertStatus(422);
    }

    public function test_checkout_requires_accepting_the_terms(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1500]);

        $this->postJson('/checkout', ['media_ids' => [$media->id], 'email' => 'vevo@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_checkout_returns_friendly_error_when_stripe_is_not_configured(): void
    {
        config(['services.stripe.secret' => '']);
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1500]);

        $response = $this->postJson('/checkout', ['media_ids' => [$media->id], 'email' => 'vevo@example.com', 'terms_accepted' => true]);

        $response->assertStatus(502);
        $this->assertStringNotContainsString('api_key', $response->json('message'));
    }

    public function test_success_page_finalizes_order_when_stripe_confirms_payment(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1500]);
        $order = Order::factory()->create(['total_cents' => 1500, 'payment_provider' => 'stripe', 'payment_provider_reference' => 'cs_test_456']);
        $order->media()->attach($media->id, ['price_cents' => 1500]);

        $this->mock(StripeCheckoutGateway::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')
                ->with('cs_test_456')
                ->once()
                ->andReturn(Session::constructFrom(['id' => 'cs_test_456', 'payment_status' => 'paid']));
        });

        $response = $this->get('/checkout/success?session_id=cs_test_456');

        $response->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(true, $response->viewData('page')['props']['paid']);
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_success_page_shows_processing_when_stripe_not_yet_paid(): void
    {
        $order = Order::factory()->create(['payment_provider' => 'stripe', 'payment_provider_reference' => 'cs_test_789']);

        $this->mock(StripeCheckoutGateway::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')
                ->with('cs_test_789')
                ->once()
                ->andReturn(Session::constructFrom(['id' => 'cs_test_789', 'payment_status' => 'unpaid']));
        });

        $response = $this->get('/checkout/success?session_id=cs_test_789');

        $response->assertOk();
        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(false, $response->viewData('page')['props']['paid']);
    }

    public function test_cancel_page_renders(): void
    {
        $this->get('/checkout/cancel')->assertOk();
    }
}
