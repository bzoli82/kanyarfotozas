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

class SimplePayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-simplepay-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        config([
            'services.simplepay.merchant' => 'PUBLICTESTHUF',
            'services.simplepay.secret_key' => self::SECRET,
            'services.simplepay.sandbox' => true,
        ]);
    }

    private function sign(string $body): string
    {
        return base64_encode(hash_hmac('sha384', $body, self::SECRET, true));
    }

    public function test_checkout_with_simplepay_provider_starts_a_transaction(): void
    {
        Http::fake([
            'sandbox.simplepay.hu/*' => Http::response([
                'transactionId' => 99887766,
                'orderRef' => 'KF-1-ABCDEFGH',
                'paymentUrl' => 'https://sandbox.simplepay.hu/pay/xyz',
            ], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);

        $response = $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'vevo@example.com',
            'terms_accepted' => true,
            'provider' => 'simplepay',
        ]);

        $response->assertOk()->assertJson(['redirect_url' => 'https://sandbox.simplepay.hu/pay/xyz']);

        $order = Order::query()->firstOrFail();
        $this->assertSame('simplepay', $order->payment_provider);
        $this->assertSame('99887766', $order->payment_provider_reference);

        Http::assertSent(fn ($request) => $request->hasHeader('Signature')
            && str_contains($request->url(), '/payment/v2/start'));
    }

    public function test_checkout_returns_friendly_error_when_simplepay_rejects(): void
    {
        Http::fake([
            'sandbox.simplepay.hu/*' => Http::response(['errorCodes' => [5011]], 200),
        ]);

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);

        $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'vevo@example.com',
            'terms_accepted' => true,
            'provider' => 'simplepay',
        ])->assertStatus(502);
    }

    public function test_return_with_valid_signature_and_success_event_finalizes_order(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);
        $order = Order::factory()->create([
            'payment_provider' => 'simplepay',
            'payment_provider_reference' => '99887766',
            'total_cents' => 2500,
        ]);
        $order->media()->attach($media->id, ['price_cents' => 2500]);

        $r = base64_encode(json_encode(['r' => 0, 't' => 99887766, 'e' => 'SUCCESS', 'm' => 'PUBLICTESTHUF', 'o' => "KF-{$order->id}-ABCDEFGH"]));

        $response = $this->get('/checkout/simplepay/return?'.http_build_query(['r' => $r, 'signature' => $this->sign($r)]));

        $response->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertTrue($response->viewData('page')['props']['paid']);
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_return_with_bad_signature_redirects_to_cancel(): void
    {
        $r = base64_encode(json_encode(['t' => 1, 'e' => 'SUCCESS']));

        $this->get('/checkout/simplepay/return?'.http_build_query(['r' => $r, 'signature' => 'forged']))
            ->assertRedirect(route('public.checkout.cancel'));
    }

    public function test_return_with_fail_event_redirects_to_cancel(): void
    {
        $order = Order::factory()->create(['payment_provider' => 'simplepay', 'payment_provider_reference' => '55']);
        $r = base64_encode(json_encode(['t' => 55, 'e' => 'FAIL', 'o' => "KF-{$order->id}-XX"]));

        $this->get('/checkout/simplepay/return?'.http_build_query(['r' => $r, 'signature' => $this->sign($r)]))
            ->assertRedirect(route('public.checkout.cancel'));

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_ipn_marks_order_paid_and_echoes_signed_confirmation(): void
    {
        Mail::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 2500]);
        $order = Order::factory()->create(['payment_provider' => 'simplepay', 'payment_provider_reference' => '777']);
        $order->media()->attach($media->id, ['price_cents' => 2500]);

        $body = json_encode([
            'salt' => 'abc',
            'orderRef' => "KF-{$order->id}-XX",
            'method' => 'CARD',
            'merchant' => 'PUBLICTESTHUF',
            'transactionId' => 777,
            'status' => 'FINISHED',
        ]);

        $response = $this->call('POST', '/api/simplepay/ipn', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Signature' => $this->sign($body),
        ], $body);

        $response->assertOk();
        $this->assertTrue($order->fresh()->isPaid());

        // A valasz tartalmazza a receiveDate-et es helyes az alairasa.
        $responseBody = $response->getContent();
        $this->assertStringContainsString('receiveDate', $responseBody);
        $this->assertSame($this->sign($responseBody), $response->headers->get('Signature'));
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_ipn_rejects_bad_signature(): void
    {
        $body = json_encode(['transactionId' => 1, 'status' => 'FINISHED']);

        $this->call('POST', '/api/simplepay/ipn', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Signature' => 'nope',
        ], $body)->assertStatus(400);
    }
}
