<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'billingo',
            'type' => Invoice::TYPE_NORMAL,
            'external_id' => (string) fake()->numberBetween(1000, 9999),
            'number' => '2026-'.fake()->numberBetween(1, 9999),
            'gross_cents' => 3000,
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
        ];
    }
}
