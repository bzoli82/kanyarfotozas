<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'contact_type' => fake()->randomElement(['support', 'photographer', 'other']),
            'status' => ContactMessage::STATUS_NEW,
        ];
    }

    public function forPhotographer(User $photographer): static
    {
        return $this->state(fn () => [
            'contact_type' => ContactMessage::TYPE_PHOTOGRAPHER,
            'photographer_id' => $photographer->id,
        ]);
    }
}
