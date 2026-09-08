<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactReply>
 */
class ContactReplyFactory extends Factory
{
    protected $model = ContactReply::class;

    public function definition(): array
    {
        return [
            'contact_message_id' => ContactMessage::factory(),
            'author_id' => User::factory(),
            'on_behalf_of_id' => null,
            'body' => fake()->paragraph(),
            'emailed' => true,
        ];
    }
}
