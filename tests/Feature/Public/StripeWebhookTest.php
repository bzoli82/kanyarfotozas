<?php

namespace Tests\Feature\Public;

use App\Mail\OrderConfirmationMail;
use App\Models\Media;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Stripe\WebhookSignature;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    private function postWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $signature = WebhookSignature::generateSignatureHeader($body, self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => $signature,
        ], $body);
    }

    public function test_valid_checkout_session_completed_event_marks_order_paid(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1500]);
        $order = Order::factory()->create(['payment_provider' => 'stripe', 'payment_provider_reference' => 'cs_test_abc']);
        $order->media()->attach($media->id, ['price_cents' => 1500]);

        $response = $this->postWebhook([
            'id' => 'evt_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_abc', 'object' => 'checkout.session']],
        ]);

        $response->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $body = json_encode(['type' => 'checkout.session.completed']);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => 't=1,v1=invalid',
        ], $body);

        $response->assertStatus(400);
    }

    public function test_unknown_session_reference_is_ignored_gracefully(): void
    {
        $response = $this->postWebhook([
            'id' => 'evt_2',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_unknown', 'object' => 'checkout.session']],
        ]);

        $response->assertOk();
    }
}
