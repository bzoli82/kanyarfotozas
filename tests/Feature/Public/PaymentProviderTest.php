<?php

namespace Tests\Feature\Public;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SimplePayGateway;
use App\Services\Payments\StripePaymentGateway;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_configured_providers_are_offered(): void
    {
        config([
            'services.stripe.secret' => '',
            'services.simplepay.merchant' => null,
            'services.simplepay.secret_key' => null,
        ]);

        $this->assertSame([], app(PaymentGatewayManager::class)->options());

        config(['services.simplepay.merchant' => 'M', 'services.simplepay.secret_key' => 'K']);

        $options = app(PaymentGatewayManager::class)->options();
        $this->assertSame(['simplepay'], array_column($options, 'id'));
    }

    public function test_cart_page_shares_payment_provider_options(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_x',
            'services.simplepay.merchant' => 'M',
            'services.simplepay.secret_key' => 'K',
        ]);

        $response = $this->get('/cart')->assertOk();
        $ids = array_column($response->viewData('page')['props']['paymentProviders'], 'id');

        $this->assertEqualsCanonicalizing(['stripe', 'simplepay'], $ids);
    }

    public function test_default_falls_back_to_first_configured_when_preferred_is_missing(): void
    {
        config([
            'payments.default' => 'stripe',
            'services.stripe.secret' => '',
            'services.simplepay.merchant' => 'M',
            'services.simplepay.secret_key' => 'K',
        ]);

        $this->assertInstanceOf(SimplePayGateway::class, app(PaymentGatewayManager::class)->default());
    }

    public function test_for_resolves_named_gateway(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(StripePaymentGateway::class, $manager->for('stripe'));
        $this->assertInstanceOf(SimplePayGateway::class, $manager->for('simplepay'));
    }
}
