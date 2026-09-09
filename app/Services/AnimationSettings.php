<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Validation\Rule;

/**
 * Mozgás / animáció beállítások — a superadmin a /admin/settings/theme „Animációk"
 * paneljén szerkeszti. A `prefers-reduced-motion` MINDIG felülír (app.css globális
 * reset), ez csak egy tartalmi finomhangolás fölötte.
 *
 * A `stílus` (preset) numerikus értékei CSS custom property-ként kerülnek az
 * app.blade.php-ba (`--anim-duration` / `--anim-distance` / `--anim-stagger`),
 * a be/ki jelzők a frontendre az Inertia `animation` shared propon.
 */
class AnimationSettings
{
    public const DEFAULT_PRESET = 'standard';

    public const DEFAULT_PAGE = 'fade';

    public const DEFAULT_HERO = 'full';

    /**
     * @var array<string, array{label: string, duration: int, distance: int, stagger: int}>
     */
    public const PRESETS = [
        'subtle' => ['label' => 'Visszafogott', 'duration' => 220, 'distance' => 6, 'stagger' => 40],
        'standard' => ['label' => 'Normál', 'duration' => 480, 'distance' => 16, 'stagger' => 70],
        'expressive' => ['label' => 'Látványos', 'duration' => 720, 'distance' => 34, 'stagger' => 110],
    ];

    public const PAGE_MODES = ['none', 'fade', 'slide'];

    public const HERO_MODES = ['none', 'kenburns', 'full'];

    public function enabled(): bool
    {
        return (bool) SiteSetting::get('anim_enabled', true);
    }

    public function preset(): string
    {
        $value = (string) SiteSetting::get('anim_preset', self::DEFAULT_PRESET);

        return array_key_exists($value, self::PRESETS) ? $value : self::DEFAULT_PRESET;
    }

    public function pageTransition(): string
    {
        $value = (string) SiteSetting::get('anim_page', self::DEFAULT_PAGE);

        return in_array($value, self::PAGE_MODES, true) ? $value : self::DEFAULT_PAGE;
    }

    public function hero(): string
    {
        $value = (string) SiteSetting::get('anim_hero', self::DEFAULT_HERO);

        return in_array($value, self::HERO_MODES, true) ? $value : self::DEFAULT_HERO;
    }

    public function scrollReveal(): bool
    {
        return (bool) SiteSetting::get('anim_reveal', true);
    }

    public function counters(): bool
    {
        return (bool) SiteSetting::get('anim_counters', true);
    }

    /**
     * A választott stílus numerikus értékei — az app.blade.php CSS custom
     * property-ként injektálja. Kikapcsolt animációnál minden 0 (= azonnali).
     *
     * @return array{'--anim-duration': string, '--anim-distance': string, '--anim-stagger': string}
     */
    public function resolvedVars(): array
    {
        if (! $this->enabled()) {
            return ['--anim-duration' => '0ms', '--anim-distance' => '0px', '--anim-stagger' => '0ms'];
        }

        $p = self::PRESETS[$this->preset()];

        return [
            '--anim-duration' => $p['duration'].'ms',
            '--anim-distance' => $p['distance'].'px',
            '--anim-stagger' => $p['stagger'].'ms',
        ];
    }

    /**
     * A frontend (reveal direktíva, számlálók, oldalváltás, hero) ez alapján dönt.
     *
     * @return array{enabled: bool, preset: string, page: string, hero: string, reveal: bool, counters: bool}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'preset' => $this->preset(),
            'page' => $this->pageTransition(),
            'hero' => $this->hero(),
            'reveal' => $this->scrollReveal(),
            'counters' => $this->counters(),
        ];
    }

    /**
     * A theme admin űrlaphoz — a toArray() + a választható opciók.
     */
    public function forForm(): array
    {
        return [
            ...$this->toArray(),
            'presetOptions' => collect(self::PRESETS)
                ->map(fn ($v, $k) => [
                    'value' => $k,
                    'label' => $v['label'],
                    'duration' => $v['duration'],
                    'distance' => $v['distance'],
                    'stagger' => $v['stagger'],
                ])
                ->values()->all(),
        ];
    }

    /**
     * `sometimes` — a téma-űrlap mindig küldi őket, de egy részleges PUT
     * (pl. teszt, script) ne írja felül a meglévő animáció-beállításokat.
     *
     * @return array<string, list<string>>
     */
    public static function validationRules(): array
    {
        return [
            'anim_enabled' => ['sometimes', 'boolean'],
            'anim_preset' => ['sometimes', Rule::in(array_keys(self::PRESETS))],
            'anim_page' => ['sometimes', Rule::in(self::PAGE_MODES)],
            'anim_hero' => ['sometimes', Rule::in(self::HERO_MODES)],
            'anim_reveal' => ['sometimes', 'boolean'],
            'anim_counters' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        foreach (['anim_enabled', 'anim_reveal', 'anim_counters'] as $key) {
            if (array_key_exists($key, $data)) {
                SiteSetting::set($key, $data[$key] ? '1' : '0');
            }
        }
        foreach (['anim_preset', 'anim_page', 'anim_hero'] as $key) {
            if (array_key_exists($key, $data)) {
                SiteSetting::set($key, (string) $data[$key]);
            }
        }
    }
}
