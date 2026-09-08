<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaShare;
use App\Models\Order;

/**
 * Megvasarolt media publikus, VIZJELES megosztasa (/share/{token}) — EPIC-17.
 * FONTOS: mindig a vizjeles elonezet-verziot osztjuk meg, sose a letoltheto,
 * vizjel nelkuli fajlt.
 */
class MediaShareService
{
    /**
     * Letrehoz (vagy ujrahasznal egy meg ervenyes) megosztasi tokent egy
     * media-hoz — csak akkor, ha a hivo bizonyitottan megvasarolta (ervenyes
     * download token, ami tartalmazza ezt a media-t).
     */
    public function createFor(Media $media, string $downloadToken, ?string $platform = null): ?MediaShare
    {
        $order = Order::query()->where('download_token', $downloadToken)->first();

        if (! $order || ! $order->isPaid() || ! $order->isTokenValid() || ! $order->media->contains($media->id)) {
            return null;
        }

        $existing = MediaShare::query()
            ->where('media_id', $media->id)
            ->where('order_id', $order->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            if ($platform && $existing->platform !== $platform) {
                $existing->forceFill(['platform' => $platform])->save();
            }

            return $existing;
        }

        return MediaShare::query()->create([
            'media_id' => $media->id,
            'order_id' => $order->id,
            'platform' => $platform,
        ]);
    }

    public function resolve(string $token): ?MediaShare
    {
        $share = MediaShare::query()
            ->with(['media.event:id,name,slug,location', 'media.photographer:id,name'])
            ->where('share_token', $token)
            ->first();

        if (! $share || $share->isExpired()) {
            return null;
        }

        $share->increment('view_count');

        return $share;
    }
}
