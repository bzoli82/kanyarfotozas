<?php

namespace App\Services;

use App\Exceptions\MediaHasSalesException;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Egy média teljes törlése: az összes legyártott/tárolt fájl + a DB rekord.
 *
 *  - eredeti + letölthető JPEG/WebP  → a `media.original_storage` szerinti disk (staging vagy archív NAS/R2)
 *  - thumbnail + vízjeles előnézet + scrub-sprite + HLS playlist/szegmensek → a publikus disk
 *  - opcionálisan az FTP-importból származó **forrás-fájl** is (`media.import_source_path` a `nas` disken)
 *
 * Minden fájlművelet `rescue`-ban fut: egy hiányzó/nem elérhető fájl nem
 * akaszthatja meg a többi törlését (a `nas` disk `throw => true`).
 */
class MediaDeleter
{
    public function __construct(private NasConnection $nas) {}

    /**
     * @throws MediaHasSalesException ha fizetett rendelés hivatkozik a médiára és nincs `$force`
     */
    public function delete(Media $media, bool $deleteImportSource = false, bool $force = false): void
    {
        if (! $force && $this->hasPaidSales($media)) {
            throw new MediaHasSalesException($media->id);
        }

        $largeFileDisk = MediaStorage::diskForOriginal($media);

        if ($largeFileDisk === 'nas' || ($deleteImportSource && filled($media->import_source_path))) {
            $this->nas->applyRuntimeConfig();
        }

        foreach (array_keys(Media::ARCHIVABLE_FIELDS) as $field) {
            if ($media->{$field}) {
                rescue(fn () => Storage::disk($largeFileDisk)->delete($media->{$field}), report: false);
            }
        }

        $public = Storage::disk(MediaStorage::public());

        foreach ([$media->watermarked_s3_key, $media->thumbnail_s3_key, $media->preview_sprite_s3_key, $media->hls_playlist_s3_key] as $key) {
            if ($key) {
                rescue(fn () => $public->delete($key), report: false);
            }
        }

        // HLS szegmensek (a media.id-alapú könyvtár egészben).
        rescue(fn () => $public->deleteDirectory("hls/{$media->id}"), report: false);

        if ($deleteImportSource && filled($media->import_source_path)) {
            rescue(fn () => Storage::disk(FtpImport::disk())->delete($media->import_source_path), report: false);
        }

        $media->delete();
    }

    public function hasPaidSales(Media $media): bool
    {
        return $media->orders()
            ->whereIn('payment_status', [Order::STATUS_PAID, Order::STATUS_REFUNDED])
            ->exists();
    }
}
