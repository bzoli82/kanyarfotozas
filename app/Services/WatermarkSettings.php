<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * A foto- es videovizjel kozos, superadmin altal testreszabhato beallitasai
 * (/admin/settings/watermark) — mindket pipeline (ImageProcessingService,
 * VideoProcessingService) ezt hasznalja, hogy a szoveg/betutipus/meret/suruseg
 * egy helyen legyen karbantartva.
 */
class WatermarkSettings
{
    /** Bundle-olt, szabadon terjesztheto (Apache-2.0 / DejaVu License) fontok. */
    public const FONTS = [
        'arimo' => ['label' => 'Arimo (Arial-szerű)', 'file' => 'Arimo-Bold.ttf'],
        'dejavu_sans' => ['label' => 'DejaVu Sans', 'file' => 'DejaVuSans-Bold.ttf'],
        'dejavu_serif' => ['label' => 'DejaVu Serif (talpas)', 'file' => 'DejaVuSerif-Bold.ttf'],
    ];

    public const DEFAULT_FONT = 'arimo';

    public const DEFAULT_SIZE = 22;

    public const DEFAULT_DENSITY = 3;

    /** Suruseg (1-5) => csempe-tavolsag pixelben. 1 = ritkas, 5 = suru. */
    public const DENSITY_SPACING = [
        1 => ['x' => 440, 'y' => 280],
        2 => ['x' => 340, 'y' => 210],
        3 => ['x' => 260, 'y' => 160],
        4 => ['x' => 190, 'y' => 120],
        5 => ['x' => 140, 'y' => 90],
    ];

    public function text(): string
    {
        // Alapból az oldal neve (SiteBranding); a vízjel oldalon felülírható.
        return (string) SiteSetting::get('watermark_text', app(SiteBranding::class)->watermarkText());
    }

    public function fontKey(): string
    {
        $key = (string) SiteSetting::get('watermark_font', self::DEFAULT_FONT);

        return array_key_exists($key, self::FONTS) ? $key : self::DEFAULT_FONT;
    }

    public function fontPath(): string
    {
        return resource_path('fonts/'.self::FONTS[$this->fontKey()]['file']);
    }

    public function size(): int
    {
        return (int) SiteSetting::get('watermark_size', self::DEFAULT_SIZE);
    }

    public function density(): int
    {
        $density = (int) SiteSetting::get('watermark_density', self::DEFAULT_DENSITY);

        return array_key_exists($density, self::DENSITY_SPACING) ? $density : self::DEFAULT_DENSITY;
    }

    /**
     * @return array{x: int, y: int}
     */
    public function spacing(): array
    {
        return self::DENSITY_SPACING[$this->density()];
    }

    public function update(string $text, string $fontKey, int $size, int $density): void
    {
        SiteSetting::set('watermark_text', $text);
        SiteSetting::set('watermark_font', array_key_exists($fontKey, self::FONTS) ? $fontKey : self::DEFAULT_FONT);
        SiteSetting::set('watermark_size', (string) $size);
        SiteSetting::set('watermark_density', (string) (array_key_exists($density, self::DENSITY_SPACING) ? $density : self::DEFAULT_DENSITY));
    }
}
