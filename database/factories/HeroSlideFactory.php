<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HeroSlide::TYPE_IMAGE,
            'image_path' => 'hero/'.$this->faker->uuid().'.webp',
            'video_path' => null,
            'poster_path' => null,
            'original_filename' => $this->faker->slug(2).'.jpg',
            'width' => 2560,
            'height' => 1440,
            'duration_seconds' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => HeroSlide::TYPE_VIDEO,
            'image_path' => null,
            'video_path' => 'hero/videos/'.$this->faker->uuid().'.mp4',
            'poster_path' => 'hero/'.$this->faker->uuid().'.webp',
            'original_filename' => $this->faker->slug(2).'.mp4',
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => 15,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
