<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * A média tároló diskek egyetlen forrása (lásd `config/media.php` -> `disks`).
 *
 * A feltöltés + feldolgozás mindig a lokális `local` diskre történik (valós
 * fájl-útvonal kell az Intervention / FFmpeg-nek); utána a kis publikus fájlok a
 * `public()` diskre, a nagy eredetik pedig az archiváló job-bal az `archive()`
 * diskre kerülnek. Élesben mindkettő Cloudflare R2 — pusztán env-beállítás.
 */
class MediaStorage
{
    /** A feltöltés + feldolgozás staging diskje — mindig lokális (valós útvonal kell). */
    public const STAGING = 'local';

    /**
     * Publikusan kiszolgált kis fájlok diskje (thumbnail, vízjeles előnézet, HLS, hero).
     */
    public static function public(): string
    {
        return config('media.disks.public', 'public');
    }

    /**
     * A nagy fájlok (eredeti + letölthető verziók) hosszú távú tárolója.
     */
    public static function archive(): string
    {
        return config('media.disks.archive', 'nas');
    }

    /**
     * A megvásárolt fájlok gyors, mindig elérhető másolatának diskje (delivery cache).
     */
    public static function delivery(): string
    {
        return config('media.disks.delivery', 'delivery');
    }

    /**
     * Van-e a staging-től elkülönülő archív réteg. Ha nincs (`archive` == `local`),
     * az archiváló job kihagyja a mozgatást és az eredeti a `local` diskon marad.
     */
    public static function hasArchiveTier(): bool
    {
        return self::archive() !== self::STAGING;
    }

    /**
     * Melyik diskon van EPP MOST a média eredeti + letölthető fájlja.
     */
    public static function diskForOriginal(Media $media): string
    {
        return $media->original_storage === Media::STORAGE_NAS
            ? self::archive()
            : self::STAGING;
    }

    /**
     * A publikus fájlok böngészőből elérhető bázis-URL-je (dev: `/storage`,
     * éles R2: az `R2_PUBLIC_URL` domain) — a frontend `mediaUrl()` helper ezt
     * kapja Inertia shared propként.
     */
    public static function publicBaseUrl(): string
    {
        $disk = self::public();
        $configured = config("filesystems.disks.{$disk}.url");

        // Lokális disk: relatív útvonalat adunk (`/storage`), így akkor is jó, ha
        // a fejlesztői szerver más porton fut, mint az APP_URL.
        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $path = filled($configured) ? (parse_url($configured, PHP_URL_PATH) ?: '/storage') : '/storage';

            return rtrim($path, '/');
        }

        if (filled($configured)) {
            return rtrim($configured, '/');
        }

        return rtrim(Storage::disk($disk)->url('/'), '/');
    }
}
