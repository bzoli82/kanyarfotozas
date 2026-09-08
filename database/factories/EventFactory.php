<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-2 months', '+2 months');

        return [
            'country_id' => Country::factory(),
            'name' => fake()->city().' Kanyar',
            'location' => fake()->city(),
            'latitude' => fake()->latitude(45.5, 48.5),
            'longitude' => fake()->longitude(16, 22.5),
            'event_date' => $startsAt->format('Y-m-d'),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+6 hours'),
            'status' => Event::STATUS_LIVE,
            'photo_price_cents' => 1490,
            'video_price_cents' => 2490,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Event::STATUS_DRAFT]);
    }

    public function announced(): static
    {
        return $this->state(fn () => ['status' => Event::STATUS_ANNOUNCED]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => Event::STATUS_ARCHIVED]);
    }
}
