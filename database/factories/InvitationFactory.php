<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => User::ROLE_PHOTOGRAPHER,
            'revenue_share_percent' => 70,
            'invited_by' => User::factory()->superadmin(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()->subDay()]);
    }
}
