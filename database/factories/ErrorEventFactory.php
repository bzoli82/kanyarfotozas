<?php

namespace Database\Factories;

use App\Models\ErrorEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorEvent>
 */
class ErrorEventFactory extends Factory
{
    protected $model = ErrorEvent::class;

    public function definition(): array
    {
        return [
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'exception_class' => 'RuntimeException',
            'message' => fake()->sentence(),
            'file' => 'app/Services/Example.php',
            'line' => fake()->numberBetween(1, 400),
            'url' => 'http://localhost/events',
            'method' => 'GET',
            'count' => fake()->numberBetween(1, 12),
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }
}
