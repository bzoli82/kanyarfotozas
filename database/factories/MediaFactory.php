<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $type = fake()->randomElement([Media::TYPE_PHOTO, Media::TYPE_PHOTO, Media::TYPE_PHOTO, Media::TYPE_VIDEO]);

        return [
            'event_id' => Event::factory(),
            'photographer_id' => User::factory()->photographer(),
            'shot_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'width' => 1920,
            'height' => 1280,
            // HUF-nak nincs valtopenze, Stripe-nal is "zero-decimal" – a price_cents itt a teljes HUF osszeg
            'price_cents' => fake()->randomElement([990, 1490, 1990, 2490]),
            'status' => Media::STATUS_READY,
            ...$this->keysForType($type),
        ];
    }

    /**
     * A media-tipustol fuggo mezok (fajl-kulcsok, sprite/idotartam) egy helyen —
     * igy a `photo()`/`video()` state-ek nem hagynak inkonzisztens rekordot
     * (pl. video, aminek nincs `preview_sprite_s3_key`-e).
     *
     * @return array<string, mixed>
     */
    private function keysForType(string $type): array
    {
        $uuid = fake()->uuid();
        $isPhoto = $type === Media::TYPE_PHOTO;

        return [
            'type' => $type,
            'original_s3_key' => "originals/{$uuid}.".($isPhoto ? 'jpg' : 'mp4'),
            'watermarked_s3_key' => "watermarked/{$uuid}.".($isPhoto ? 'webp' : 'mp4'),
            'thumbnail_s3_key' => "thumbnails/{$uuid}.webp",
            'download_jpeg_s3_key' => $isPhoto ? "downloads/{$uuid}.jpg" : null,
            'download_webp_s3_key' => $isPhoto ? "downloads/{$uuid}.webp" : null,
            'preview_sprite_s3_key' => $isPhoto ? null : "sprites/{$uuid}.png",
            'preview_sprite_interval' => $isPhoto ? null : 2,
            'duration_seconds' => $isPhoto ? null : fake()->numberBetween(4, 45),
        ];
    }

    public function photo(): static
    {
        return $this->state(fn () => $this->keysForType(Media::TYPE_PHOTO));
    }

    public function video(): static
    {
        return $this->state(fn () => $this->keysForType(Media::TYPE_VIDEO));
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Media::STATUS_PROCESSING]);
    }
}
