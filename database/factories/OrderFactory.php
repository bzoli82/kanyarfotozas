<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'buyer_email' => fake()->safeEmail(),
            'total_cents' => fake()->numberBetween(990, 5000),
            'payment_status' => Order::STATUS_PENDING,
            'discount_cents' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => Order::STATUS_PAID,
            'payment_provider' => 'stripe',
            'payment_provider_reference' => 'cs_test_'.fake()->uuid(),
        ]);
    }
}
