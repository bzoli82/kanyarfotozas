<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nyilvanos media reprezentacio — csak a vasarlo elott is lathato mezok
 * (soha nem az original/download_* kulcsok, azok privat S3/NAS utvonalak).
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'thumbnail_s3_key' => $this->thumbnail_s3_key,
            'watermarked_s3_key' => $this->watermarked_s3_key,
            'hls_playlist_s3_key' => $this->hls_playlist_s3_key,
            'preview_sprite_s3_key' => $this->preview_sprite_s3_key,
            'preview_sprite_interval' => $this->preview_sprite_interval,
            'duration_seconds' => $this->duration_seconds,
            'shot_at' => $this->shot_at?->toIso8601String(),
            'price_cents' => $this->price_cents,
            'width' => $this->width,
            'height' => $this->height,
            'plate_blurred' => (bool) $this->license_plate_blurred,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'location' => $this->event->location,
            ]),
            'photographer' => $this->whenLoaded('photographer', fn () => [
                'name' => $this->photographer->name,
            ]),
        ];
    }
}
