<?php

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    protected $model = FaqItem::class;

    public function definition(): array
    {
        return [
            'question_hu' => rtrim(fake()->sentence(), '.').'?',
            'answer_hu' => fake()->paragraph(),
            'category' => fake()->randomElement(['general', 'payment', 'download', 'video']),
            'sort_order' => fake()->numberBetween(1, 20),
            'active' => true,
            'is_homepage' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function category(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
