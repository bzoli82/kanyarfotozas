<?php

namespace App\Services\Payments;

use App\Services\PaymentSettings;
use InvalidArgumentException;

/**
 * A fizetési szolgáltatók feloldása név szerint + a kosárnak felkínálható
 * (konfigurált) lista. A szolgáltatók a konténerből jönnek, így tesztben mockolhatók.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'stripe' => StripePaymentGateway::class,
        'simplepay' => SimplePayGateway::class,
        'barion' => BarionGateway::class,
    ];

    /**
     * Emberi nevek a kosár szolgáltató-választójához.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'stripe' => 'Bankkártya (Stripe)',
        'simplepay' => 'Bankkártya – SimplePay',
        'barion' => 'Bankkártya – Barion',
    ];

    public function for(string $provider): PaymentGateway
    {
        $class = self::GATEWAYS[$provider]
            ?? throw new InvalidArgumentException("Ismeretlen fizetési szolgáltató: {$provider}");

        return app($class);
    }

    public function default(): PaymentGateway
    {
        $available = $this->available();
        $preferred = (string) config('payments.default', 'stripe');

        if (isset($available[$preferred])) {
            return $available[$preferred];
        }

        // Ha a beállított alapértelmezett nincs elérhető, az első elérhető (végső esetben a beállított).
        return array_values($available)[0] ?? $this->for($preferred);
    }

    /**
     * Konfigurált ÉS a superadmin által be nem kapcsolt szolgáltatók — ezt kínálja a pénztár.
     *
     * @return array<string, PaymentGateway>
     */
    public function available(): array
    {
        $settings = app(PaymentSettings::class);

        return array_filter($this->configured(), fn ($g, $id) => $settings->providerEnabled($id), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, PaymentGateway> provider => gateway
     */
    public function configured(): array
    {
        $result = [];

        foreach (array_keys(self::GATEWAYS) as $provider) {
            $gateway = $this->for($provider);
            if ($gateway->isConfigured()) {
                $result[$provider] = $gateway;
            }
        }

        return $result;
    }

    /**
     * A kosár szolgáltató-választójához: `[['id' => 'stripe', 'label' => '...'], ...]`.
     *
     * @return list<array{id: string, label: string}>
     */
    public function options(): array
    {
        return array_map(
            fn (string $id) => ['id' => $id, 'label' => self::LABELS[$id] ?? $id],
            array_keys($this->available()),
        );
    }
}
