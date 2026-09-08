<?php

namespace Database\Factories;

use App\Models\PhotographerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotographerPayout>
 */
class PhotographerPayoutFactory extends Factory
{
    protected $model = PhotographerPayout::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 50000);

        return [
            'photographer_id' => User::factory()->state(['role' => User::ROLE_PHOTOGRAPHER]),
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'gross_cents' => $amount * 2,
            'amount_cents' => $amount,
            'media_count' => fake()->numberBetween(1, 20),
            'status' => PhotographerPayout::STATUS_DRAFT,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PhotographerPayout::STATUS_PAID,
            'method' => 'banki átutalás',
            'paid_at' => now()->subDays(3),
        ]);
    }
}
