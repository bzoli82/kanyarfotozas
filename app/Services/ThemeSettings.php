<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Superadmin altal testreszabhato megjelenes-beallitasok (/admin/settings/theme):
 *
 *  - **Sema (preset)**: elore beallitott vilagos+sotet szinparok (`default` = a
 *    jelenlegi „Aszfalt" — ez az alap), plusz egy `custom` sema, ahol minden szin
 *    kezzel allithato mindket modhoz.
 *  - **Akcentszin / sarok-lekerekites / betutipus**: a semara raulo globalis ertekek.
 *  - **Alapertelmezett mod**: sotet / vilagos / rendszer (a latogato a ThemeToggle-lel
 *    sajat maga is felulbirhatja, localStorage-ban — lasd resources/js/Stores/theme.js).
 *
 * A felbontott palettat (`resolvedPalettes()`) az app.blade.php inline <style>-je
 * injektalja CSS custom property-kkent (`--color-surface-*` / `--color-border` /
 * `--color-content` / `--color-muted`), mind a vilagos, mind a sotet modhoz.
 */
class ThemeSettings
{
    public const MODES = ['dark', 'light', 'system'];

    public const DEFAULT_MODE = 'dark';

    public const DEFAULT_ACCENT_COLOR = '#e63946';

    public const DEFAULT_BORDER_RADIUS = 12;

    public const DEFAULT_PRESET = 'default';

    /** A paletta-kulcsok, amiket mindket mod (light/dark) megad. */
    public const PALETTE_KEYS = ['surface_0', 'surface_1', 'surface_2', 'border', 'content', 'muted'];

    public const FONTS = [
        'Inter' => 'Inter, ui-sans-serif, system-ui, sans-serif',
        'Space Grotesk' => "'Space Grotesk', 'Inter', ui-sans-serif, system-ui, sans-serif",
        'Poppins' => 'Poppins, ui-sans-serif, system-ui, sans-serif',
    ];

    public const DEFAULT_FONT = 'Inter';

    /**
     * Beepitett semak: vilagos + sotet szinpar + ajanlott akcentszin.
     * A `default` a jelenlegi megjelenes — ez marad az alapertelmezes.
     *
     * @var array<string, array{label: string, accent: string, light: array<string, string>, dark: array<string, string>}>
     */
    public const PRESETS = [
        'default' => [
            'label' => 'Aszfalt (alap)',
            'accent' => '#e63946',
            'light' => ['surface_0' => '#f7f7f8', 'surface_1' => '#ffffff', 'surface_2' => '#eef0f2', 'border' => '#dcdfe3', 'content' => '#16181c', 'muted' => '#6b7280'],
            'dark' => ['surface_0' => '#0d0d0d', 'surface_1' => '#141414', 'surface_2' => '#1a1a1a', 'border' => '#2a2a2a', 'content' => '#f0f0f0', 'muted' => '#8a8a8a'],
        ],
        'racing' => [
            'label' => 'Verseny (borostyán)',
            'accent' => '#f59e0b',
            'light' => ['surface_0' => '#faf8f3', 'surface_1' => '#ffffff', 'surface_2' => '#f1ede3', 'border' => '#e4dcc9', 'content' => '#1c1810', 'muted' => '#7a6f57'],
            'dark' => ['surface_0' => '#12100c', 'surface_1' => '#1a1712', 'surface_2' => '#221e17', 'border' => '#352e22', 'content' => '#f5f0e6', 'muted' => '#8a7f6a'],
        ],
        'ocean' => [
            'label' => 'Óceán (kék)',
            'accent' => '#3b82f6',
            'light' => ['surface_0' => '#f1f6fc', 'surface_1' => '#ffffff', 'surface_2' => '#e5eef8', 'border' => '#cfddef', 'content' => '#0f1b2d', 'muted' => '#5b7391'],
            'dark' => ['surface_0' => '#0b1220', 'surface_1' => '#111a2e', 'surface_2' => '#17233b', 'border' => '#27364f', 'content' => '#e8eef7', 'muted' => '#7089ad'],
        ],
        'forest' => [
            'label' => 'Erdő (zöld)',
            'accent' => '#22c55e',
            'light' => ['surface_0' => '#f1f8f3', 'surface_1' => '#ffffff', 'surface_2' => '#e5f1e9', 'border' => '#cee3d5', 'content' => '#0f1c14', 'muted' => '#5f7a67'],
            'dark' => ['surface_0' => '#0c130f', 'surface_1' => '#121b15', 'surface_2' => '#18241c', 'border' => '#27362b', 'content' => '#e9f1eb', 'muted' => '#77937f'],
        ],
        'grape' => [
            'label' => 'Szőlő (lila)',
            'accent' => '#a855f7',
            'light' => ['surface_0' => '#f6f3fb', 'surface_1' => '#ffffff', 'surface_2' => '#ece4f6', 'border' => '#ddcfec', 'content' => '#1a1226', 'muted' => '#6f5f8c'],
            'dark' => ['surface_0' => '#100c18', 'surface_1' => '#171226', 'surface_2' => '#221a33', 'border' => '#332a47', 'content' => '#efe9f5', 'muted' => '#8b7ba5'],
        ],
        'slate' => [
            'label' => 'Pala (indigó)',
            'accent' => '#6366f1',
            'light' => ['surface_0' => '#f5f6f8', 'surface_1' => '#ffffff', 'surface_2' => '#eaecef', 'border' => '#d8dbe1', 'content' => '#181b23', 'muted' => '#646b7d'],
            'dark' => ['surface_0' => '#0f1117', 'surface_1' => '#161922', 'surface_2' => '#1d212c', 'border' => '#2c313f', 'content' => '#eceef3', 'muted' => '#7b8397'],
        ],
    ];

    public function mode(): string
    {
        $mode = (string) SiteSetting::get('theme_mode', self::DEFAULT_MODE);

        return in_array($mode, self::MODES, true) ? $mode : self::DEFAULT_MODE;
    }

    /** @return list<string> */
    public static function presetKeys(): array
    {
        return [...array_keys(self::PRESETS), 'custom'];
    }

    public function preset(): string
    {
        $preset = (string) SiteSetting::get('theme_preset', self::DEFAULT_PRESET);

        return in_array($preset, self::presetKeys(), true) ? $preset : self::DEFAULT_PRESET;
    }

    public function accentColor(): string
    {
        $stored = (string) SiteSetting::get('accent_color', '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $stored) ? $stored : self::DEFAULT_ACCENT_COLOR;
    }

    /** Automatikusan sotetitett akcentszin hover-allapothoz — nincs kulon admin mezo raja. */
    public function accentHoverColor(): string
    {
        return $this->darken($this->accentColor(), 0.15);
    }

    public function borderRadius(): int
    {
        return (int) SiteSetting::get('border_radius', self::DEFAULT_BORDER_RADIUS);
    }

    public function fontKey(): string
    {
        $key = (string) SiteSetting::get('font_family', self::DEFAULT_FONT);

        return array_key_exists($key, self::FONTS) ? $key : self::DEFAULT_FONT;
    }

    public function fontStack(): string
    {
        return self::FONTS[$this->fontKey()];
    }

    /**
     * A `custom` sema tarolt palettaja (vilagos + sotet) — hianyzo kulcsok a
     * `default` semabol potolva.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function customPalettes(): array
    {
        $stored = json_decode((string) SiteSetting::get('theme_custom', ''), true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'light' => $this->sanitizePalette($stored['light'] ?? [], self::PRESETS['default']['light']),
            'dark' => $this->sanitizePalette($stored['dark'] ?? [], self::PRESETS['default']['dark']),
        ];
    }

    /**
     * A ténylegesen érvényes vilagos + sotet paletta (a semabol vagy a custom-bol),
     * az akcentszinnel es a hover-valtozataval kiegeszitve — ezt injektalja a blade.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function resolvedPalettes(): array
    {
        $preset = $this->preset();

        $base = $preset === 'custom'
            ? $this->customPalettes()
            : ['light' => self::PRESETS[$preset]['light'], 'dark' => self::PRESETS[$preset]['dark']];

        $accent = $this->accentColor();
        $accentHover = $this->accentHoverColor();

        foreach (['light', 'dark'] as $mode) {
            $base[$mode]['accent'] = $accent;
            $base[$mode]['accent_hover'] = $accentHover;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode(),
            'preset' => $this->preset(),
            'accent_color' => $this->accentColor(),
            'accent_hover_color' => $this->accentHoverColor(),
            'border_radius' => $this->borderRadius(),
            'font_family' => $this->fontKey(),
            'custom' => $this->customPalettes(),
            'presets' => collect(self::PRESETS)
                ->map(fn (array $p, string $key) => ['key' => $key] + $p)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        $preset = in_array($data['preset'] ?? null, self::presetKeys(), true)
            ? (string) $data['preset']
            : $this->preset();

        SiteSetting::set('theme_preset', $preset);
        SiteSetting::set('theme_mode', in_array($data['mode'] ?? null, self::MODES, true) ? $data['mode'] : self::DEFAULT_MODE);
        SiteSetting::set('accent_color', preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($data['accent_color'] ?? '')) ? $data['accent_color'] : self::DEFAULT_ACCENT_COLOR);
        SiteSetting::set('border_radius', (string) (int) ($data['border_radius'] ?? self::DEFAULT_BORDER_RADIUS));
        SiteSetting::set('font_family', array_key_exists($data['font_family'] ?? null, self::FONTS) ? $data['font_family'] : self::DEFAULT_FONT);

        if ($preset === 'custom') {
            $custom = is_array($data['custom'] ?? null) ? $data['custom'] : [];
            SiteSetting::set('theme_custom', json_encode([
                'light' => $this->sanitizePalette($custom['light'] ?? [], self::PRESETS['default']['light']),
                'dark' => $this->sanitizePalette($custom['dark'] ?? [], self::PRESETS['default']['dark']),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $palette
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function sanitizePalette(array $palette, array $fallback): array
    {
        $result = [];

        foreach (self::PALETTE_KEYS as $key) {
            $value = (string) ($palette[$key] ?? '');
            $result[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback[$key];
        }

        return $result;
    }

    /** @param float $amount 0-1 kozotti ertek, mennyivel legyen sotetebb az eredeti szinnel szemben. */
    private function darken(string $hexColor, float $amount): string
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) !== 6) {
            return $hexColor;
        }

        [$r, $g, $b] = array_map(fn (string $part) => (int) hexdec($part), str_split($hex, 2));

        $r = (int) max(0, round($r * (1 - $amount)));
        $g = (int) max(0, round($g * (1 - $amount)));
        $b = (int) max(0, round($b * (1 - $amount)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
