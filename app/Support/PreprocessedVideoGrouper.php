<?php

namespace App\Support;

/**
 * Elő-feldolgozott videó-feltöltés (config `media.video_mode` = `preprocessed`)
 * fájl-hármasainak összepárosítása közös alapnév szerint:
 *
 *   foo.mp4        – teljes felbontású eredeti (a termék)
 *   foo_lores.mp4  – kis felbontású, vízjelezett előnézet
 *   foo.jpg        – állókép poszter (opcionális)
 *
 * A csoportosítás fájlnév-alapú, így ugyanúgy működik a webes batch feltöltésre
 * (UploadedFile) és az FTP-importra (távoli útvonalak) is — a hívó egy
 * `fájlnév => tetszőleges azonosító` map-et ad át.
 */
class PreprocessedVideoGrouper
{
    /** @var list<string> A „mester" (eladható) videó kiterjesztései. */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi'];

    /** @var list<string> Poszter-képként elfogadott kiterjesztések. */
    public const POSTER_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * @template T
     *
     * @param  array<string, T>  $files  fájlnév => azonosító (pl. UploadedFile vagy távoli útvonal)
     * @return array{
     *     videos: list<array{stem: string, master: T, lores: T|null, poster: T|null}>,
     *     photos: list<T>,
     *     orphan_lores: list<string>
     * }
     */
    public static function group(array $files): array
    {
        $suffix = (string) config('media.preprocessed_lores_suffix', '_lores');

        $masters = [];
        $lores = [];
        $posters = [];

        foreach ($files as $name => $handle) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $stem = pathinfo($name, PATHINFO_FILENAME);

            if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                if ($suffix !== '' && str_ends_with($stem, $suffix)) {
                    $lores[substr($stem, 0, -strlen($suffix))] = $handle;
                } else {
                    $masters[$stem] = $handle;
                }

                continue;
            }

            if (in_array($extension, self::POSTER_EXTENSIONS, true)) {
                $posters[$stem] = $handle;
            }
        }

        $videos = [];

        foreach ($masters as $stem => $master) {
            $videos[] = [
                'stem' => $stem,
                'master' => $master,
                'lores' => $lores[$stem] ?? null,
                'poster' => $posters[$stem] ?? null,
            ];
        }

        $photos = [];

        foreach ($posters as $stem => $handle) {
            if (! isset($masters[$stem])) {
                $photos[] = $handle;
            }
        }

        $orphanLores = [];

        foreach ($lores as $stem => $handle) {
            if (! isset($masters[$stem])) {
                $orphanLores[] = $stem.$suffix;
            }
        }

        return ['videos' => $videos, 'photos' => $photos, 'orphan_lores' => $orphanLores];
    }
}
