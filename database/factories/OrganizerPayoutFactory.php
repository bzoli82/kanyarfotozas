<?php

namespace Database\Factories;

use App\Models\OrganizerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizerPayout>
 */
class OrganizerPayoutFactory extends Factory
{
    protected $model = OrganizerPayout::class;

    public function definition(): array
    {
        return [
            'organizer_id' => User::factory()->organizer(),
            'event_id' => null,
            'amount_cents' => fake()->numberBetween(5000, 200000),
            'reference' => fake()->optional()->bothify('UT-####'),
            'note' => null,
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'created_by' => User::factory()->superadmin(),
        ];
    }
}
