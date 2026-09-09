<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Kézbesítési gyorsítótár (delivery cache).
 *
 * A megvásárolt, letölthető fájlokat (fotó JPEG+WebP, videó MP4) a fizetés után
 * átmásoljuk az archív rétegről (NAS SFTP / R2 privát) egy gyors, mindig elérhető
 * lokális `delivery` diskre. A vásárló-oldali letöltés ezután ettől a disktől
 * függ, nem a lassú/időnként elérhetetlen archívtól. A másolatot a
 * `roadsidephoto:purge-delivery-cache` parancs takarítja (lejárt/kimerült token).
 *
 * A „forensic" (láthatatlan) jelet NEM itt tesszük a fájlba — a gyorsítótár a
 * tiszta fájlt tárolja, a per-vásárló jel a kiszolgáláskor, memóriában kerül rá.
 */
class OrderFulfillment
{
    /**
     * A rendelés letölthető (media, formátum) párjai.
     *
     * @return list<array{media: Media, format: string}>
     */
    public function items(Order $order): array
    {
        $items = [];

        foreach ($order->media as $media) {
            foreach ($this->formatsFor($media) as $format) {
                $items[] = ['media' => $media, 'format' => $format];
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function formatsFor(Media $media): array
    {
        return $media->isVideo() ? ['mp4'] : ['jpeg', 'webp'];
    }

    /**
     * Az archív rétegen lévő forrás-fájl kulcsa az adott formátumhoz.
     */
    public function originKey(Media $media, string $format, Order $order): ?string
    {
        // EPIC-13: ha a rendszám homályosítva lett, de a vásárló hozzájárult az
        // eredetihez, a homályosítatlan eredetit adjuk (fotónál).
        if ($media->isPhoto() && $media->license_plate_blurred && $order->plate_consent && in_array($format, ['jpeg', 'webp'], true)) {
            return $media->original_s3_key;
        }

        return match ($format) {
            'jpeg' => $media->isPhoto() ? $media->download_jpeg_s3_key : null,
            'webp' => $media->isPhoto() ? $media->download_webp_s3_key : null,
            'mp4' => $media->isVideo() ? $media->original_s3_key : null,
            default => null,
        };
    }

    public function originDisk(Media $media): string
    {
        $disk = MediaStorage::diskForOriginal($media);

        if ($disk === 'nas') {
            app(NasConnection::class)->applyRuntimeConfig();
        }

        return $disk;
    }

    public function deliveryDisk(): string
    {
        return MediaStorage::delivery();
    }

    public function deliveryKey(Order $order, Media $media, string $format): string
    {
        $extension = match ($format) {
            'jpeg' => 'jpg',
            default => $format,
        };

        return "orders/{$order->id}/{$media->id}.{$extension}";
    }

    /**
     * Egy megvásárolt fájl kiszolgálási forrása MOST: `[disk, key]`.
     *
     * Előbb a delivery gyorsítótár; ha ott nincs, az archív réteg (és a háttérben
     * elindítjuk a gyorsítótárazást). `null`, ha sehol sem érhető el.
     *
     * @return array{0: string, 1: string}|null
     */
    public function resolveSource(Order $order, Media $media, string $format): ?array
    {
        $deliveryDisk = $this->deliveryDisk();
        $deliveryKey = $this->deliveryKey($order, $media, $format);

        if (Storage::disk($deliveryDisk)->exists($deliveryKey)) {
            return [$deliveryDisk, $deliveryKey];
        }

        $originKey = $this->originKey($media, $format, $order);

        if (blank($originKey)) {
            return null;
        }

        $originDisk = $this->originDisk($media);

        if (rescue(fn () => Storage::disk($originDisk)->exists($originKey), false, false)) {
            return [$originDisk, $originKey];
        }

        return null;
    }

    /**
     * A megvásárolt fájlok átmásolása az archív rétegről a `delivery` diskre.
     * Beállítja a rendelés `fulfillment_*` mezőit. `true`, ha minden fájl a helyén.
     */
    public function prepare(Order $order): bool
    {
        if (! $order->isPaid()) {
            return false;
        }

        $order->loadMissing('media');

        $deliveryDisk = Storage::disk($this->deliveryDisk());
        $allOk = true;
        $missing = [];

        foreach ($this->items($order) as ['media' => $media, 'format' => $format]) {
            $deliveryKey = $this->deliveryKey($order, $media, $format);

            if ($deliveryDisk->exists($deliveryKey)) {
                continue;
            }

            $originKey = $this->originKey($media, $format, $order);

            if (blank($originKey)) {
                continue; // ehhez a formátumhoz nincs fájl (pl. videóhoz nincs webp)
            }

            $originDisk = $this->originDisk($media);
            $contents = rescue(fn () => Storage::disk($originDisk)->get($originKey), null, false);

            if ($contents === null) {
                $allOk = false;
                $missing[] = "#{$media->id}/{$format}";

                continue;
            }

            rescue(fn () => $deliveryDisk->put($deliveryKey, $contents), report: false);
        }

        $order->forceFill([
            'fulfillment_status' => $allOk ? 'ready' : ($order->fulfillment_attempts >= 7 ? 'failed' : 'pending'),
            'fulfillment_prepared_at' => $allOk ? now() : $order->fulfillment_prepared_at,
            'fulfillment_attempts' => $order->fulfillment_attempts + 1,
            'fulfillment_error' => $allOk ? null : ('Nem elérhető: '.implode(', ', $missing)),
        ])->save();

        return $allOk;
    }

    /**
     * A rendelés gyorsítótárazott fájljainak törlése.
     */
    public function purge(Order $order): void
    {
        rescue(fn () => Storage::disk($this->deliveryDisk())->deleteDirectory("orders/{$order->id}"), report: false);

        if ($order->fulfillment_status !== 'pending' || filled($order->fulfillment_prepared_at)) {
            $order->forceFill([
                'fulfillment_status' => 'pending',
                'fulfillment_prepared_at' => null,
                'fulfillment_attempts' => 0,
                'fulfillment_error' => null,
            ])->save();
        }
    }
}
