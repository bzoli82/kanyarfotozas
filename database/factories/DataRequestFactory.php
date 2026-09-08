<?php

namespace Database\Factories;

use App\Models\DataRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataRequest>
 */
class DataRequestFactory extends Factory
{
    protected $model = DataRequest::class;

    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'type' => fake()->randomElement([DataRequest::TYPE_EXPORT, DataRequest::TYPE_DELETE]),
            'status' => DataRequest::STATUS_PENDING,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => DataRequest::STATUS_VERIFIED, 'verified_at' => now()]);
    }
}
