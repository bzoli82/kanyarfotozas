<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Kepfeldolgozo pipeline (EPIC-04): egy feltoltott eredeti fenykepbol
 * legyartja a 4 szukseges valtozatot. GD drivert hasznal (nincs Imagick
 * telepitve ezen a gepen). A vizjel szovege/betutipusa/merete/surusege a
 * superadmin altal testreszabhato (lasd WatermarkSettings).
 */
class ImageProcessingService
{
    private ImageManager $manager;

    public function __construct(private WatermarkSettings $watermark)
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * 400x300 WebP 75% — publikus galeria thumbnail.
     */
    public function makeThumbnail(string $absolutePath): string
    {
        $image = $this->manager->decodePath($absolutePath);
        $image->cover(400, 300);

        return (string) $image->encode(new WebpEncoder(quality: 75));
    }

    /**
     * Max 1200px szeles WebP 80%, csempezett vizjellel — publikus, ingyenes elonezet.
     * `$blurBox` (a FORRAS kep pixeleiben: {x,y,w,h}) eseten a regiot elhomalyositja
     * (EPIC-13 rendszam-homalyositas) — meg a vizjel felvitele elott.
     *
     * @param  array{x: int, y: int, w: int, h: int}|null  $blurBox
     */
    public function makeWatermarkedPreview(string $absolutePath, ?array $blurBox = null): string
    {
        $image = $this->manager->decodePath($absolutePath);
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();

        $image->scaleDown(width: 1200);

        if ($blurBox) {
            $this->blurRegion($image, $blurBox, $sourceWidth, $sourceHeight);
        }

        $this->applyTiledWatermark($image);

        return (string) $image->encode(new WebpEncoder(quality: 80));
    }

    /**
     * Eredeti felbontas, JPEG 92%, vizjel NELKUL — csak fizetes utan elerheto.
     *
     * @param  array{x: int, y: int, w: int, h: int}|null  $blurBox
     */
    public function makeDownloadJpeg(string $absolutePath, ?array $blurBox = null): string
    {
        $image = $this->manager->decodePath($absolutePath);

        if ($blurBox) {
            $this->blurRegion($image, $blurBox, $image->width(), $image->height());
        }

        return (string) $image->encode(new JpegEncoder(quality: 92));
    }

    /**
     * Eredeti felbontas, WebP 90%, vizjel NELKUL — csak fizetes utan elerheto.
     *
     * @param  array{x: int, y: int, w: int, h: int}|null  $blurBox
     */
    public function makeDownloadWebp(string $absolutePath, ?array $blurBox = null): string
    {
        $image = $this->manager->decodePath($absolutePath);

        if ($blurBox) {
            $this->blurRegion($image, $blurBox, $image->width(), $image->height());
        }

        return (string) $image->encode(new WebpEncoder(quality: 90));
    }

    /**
     * Egy teglalap-regio elhomalyositasa (EPIC-13). A `$box` a
     * `$sourceWidth`/`$sourceHeight` meretu forras-koordinatarendszerben van,
     * ezt skalazzuk a jelenlegi `$image` meretere. A regiot kikodolt bajtokon
     * keresztul masoljuk ki (a GD driver `clone`-ja nem mindig mely masolat).
     *
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    private function blurRegion(ImageInterface $image, array $box, int $sourceWidth, int $sourceHeight): void
    {
        $scaleX = $image->width() / max(1, $sourceWidth);
        $scaleY = $image->height() / max(1, $sourceHeight);

        $pad = 6;
        $x = max(0, (int) floor($box['x'] * $scaleX) - $pad);
        $y = max(0, (int) floor($box['y'] * $scaleY) - $pad);
        $w = min($image->width() - $x, (int) ceil($box['w'] * $scaleX) + 2 * $pad);
        $h = min($image->height() - $y, (int) ceil($box['h'] * $scaleY) + 2 * $pad);

        if ($w < 2 || $h < 2) {
            return;
        }

        $region = $this->manager->decode((string) $image->encode(new PngEncoder));
        $region->crop($w, $h, $x, $y);
        $region->blur((int) config('media.plate_blur_strength', 45));

        $image->insert($region, $x, $y);
    }

    /**
     * Fooldali hero hatterkep: max 2560px szeles WebP 82%, vizjel NELKUL —
     * a superadmin altal feltoltott kepekbol (lasd HeroSlide / Admin\HeroSlideController).
     *
     * @return array{binary: string, width: int, height: int}
     */
    public function makeHeroImage(string $absolutePath): array
    {
        $image = $this->manager->decodePath($absolutePath);
        $image->scaleDown(width: 2560);

        return [
            'binary' => (string) $image->encode(new WebpEncoder(quality: 82)),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Fotós profilkép: 512x512 négyzetes WebP 82%, vízjel NÉLKÜL — a nyilvános
     * Fotósok oldalhoz és az admin fotós-profilhoz.
     */
    public function makeAvatar(string $absolutePath): string
    {
        $image = $this->manager->decodePath($absolutePath);
        $image->cover(512, 512);

        return (string) $image->encode(new WebpEncoder(quality: 82));
    }

    /**
     * Feltöltött raszter-logó normalizálása: max 900px széles, arány megtartva,
     * átlátszóságot megőrző WebP 92%. (SVG-t nem ez kezel — az sanitálva, nyersen tárolódik.)
     */
    public function makeLogo(string $absolutePath): string
    {
        $image = $this->manager->decodePath($absolutePath);
        $image->scaleDown(width: 900);

        return (string) $image->encode(new WebpEncoder(quality: 92));
    }

    /**
     * @return array{width: int, height: int}
     */
    public function dimensions(string $absolutePath): array
    {
        $image = $this->manager->decodePath($absolutePath);

        return ['width' => $image->width(), 'height' => $image->height()];
    }

    /**
     * EXIF DateTimeOriginal kiolvasasa, ha van (a fotozas tenyleges idopontja).
     * Sok szerkesztett/webre optimalizalt kep mar nem tartalmaz EXIF-et — ekkor null.
     */
    public function readShotAt(string $absolutePath): ?\DateTimeImmutable
    {
        if (! function_exists('exif_read_data') || ! in_array(exif_imagetype($absolutePath), [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM], true)) {
            return null;
        }

        $exif = @exif_read_data($absolutePath);
        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        if (! $raw) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw);

        return $parsed ?: null;
    }

    /**
     * Kesz WebP elonezetkep egy tetszoleges (pl. minta-) kepre, a megadott
     * vizjel-beallitasokkal — a superadmin admin feluleti elonezetehez
     * hasznaljuk, ahol a meg el nem mentett urlap-ertekeket kell megjeleniteni.
     */
    public function previewWithSettings(string $absolutePath, string $text, string $fontPath, int $size, array $spacing): string
    {
        $image = $this->manager->decodePath($absolutePath);
        $image->scaleDown(width: 800);
        $this->applyTiledWatermark($image, $text, $fontPath, $size, $spacing);

        return (string) $image->encode(new WebpEncoder(quality: 80));
    }

    /**
     * Csempezett, 45 fokban forgatott, felig atlatszo vizjel-szoveg az egesz
     * kepen vegig — a spec szerint a teljes elonezetet le kell fednie, hogy
     * vagassal ne legyen eltavolithato. Alapertelmezetten a mentett
     * (WatermarkSettings) ertekeket hasznalja, de felulirhato (pl. elonezethez).
     */
    private function applyTiledWatermark(
        ImageInterface $image,
        ?string $text = null,
        ?string $fontPath = null,
        ?int $size = null,
        ?array $spacing = null,
    ): void {
        $text ??= $this->watermark->text();
        $fontPath ??= $this->watermark->fontPath();
        $size ??= $this->watermark->size();
        $spacing ??= $this->watermark->spacing();

        for ($y = -$spacing['y']; $y < $image->height() + $spacing['y']; $y += $spacing['y']) {
            for ($x = -$spacing['x']; $x < $image->width() + $spacing['x']; $x += $spacing['x']) {
                $image->text($text, $x, $y, function ($font) use ($fontPath, $size) {
                    $font->filename($fontPath);
                    $font->size($size);
                    $font->color('rgba(255, 255, 255, 0.35)');
                    $font->angle(45);
                    $font->align('center', 'center');
                });
            }
        }
    }
}
