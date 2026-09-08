<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Str;

/**
 * Konyvtarstruktura a NAS-on (SFTP disk 'nas'):
 *
 *   kanyarfotozas/{esemeny datuma: YYYY-MM-DD}/{helyszin-slug}/{fotos-slug}/{media_id}_{suffix}.{ext}
 *
 * Pl.: kanyarfotozas/2026-09-04/eger/kovacs-peter/142_original.jpg
 *
 * A gyoker ('kanyarfotozas') maga a 'nas' disk 'root' beallitasabol jon (config/filesystems.php),
 * ezert az itt generalt utvonalak mar a gyokerhez kepest relativak.
 */
class NasPathGenerator
{
    /**
     * A media egy adott "nagy" mezojehez (original/download_jpeg/download_webp) tartozo
     * celt path a NAS-on, a forras fajl kiterjesztese alapjan (ha a mezohoz nincs fix kiterjesztes).
     */
    public function pathFor(Media $media, string $field, string $sourceExtension): string
    {
        $config = Media::ARCHIVABLE_FIELDS[$field]
            ?? throw new \InvalidArgumentException("Ismeretlen archivalando mezo: {$field}");

        $extension = $config['extension'] ?? strtolower(ltrim($sourceExtension, '.'));

        return sprintf(
            '%s/%d_%s.%s',
            $this->directoryFor($media),
            $media->id,
            $config['suffix'],
            $extension,
        );
    }

    /**
     * A media esemenyehez/fotosahoz tartozo konyvtar (datum/helyszin/fotos), a gyoker nelkul.
     */
    public function directoryFor(Media $media): string
    {
        $media->loadMissing(['event', 'photographer']);

        $date = $media->event?->event_date?->format('Y-m-d') ?? 'ismeretlen-datum';
        $location = $media->event ? Str::slug($media->event->location) : 'ismeretlen-helyszin';
        $photographer = $media->photographer ? Str::slug($media->photographer->name) : 'ismeretlen-fotos';

        return "{$date}/{$location}/{$photographer}";
    }
}
