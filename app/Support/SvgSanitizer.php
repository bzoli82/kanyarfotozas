<?php

namespace App\Support;

/**
 * Minimál SVG-tisztító a feltöltött logóhoz. A logót mindenhol `<img src>`-ként
 * jelenítjük meg (így a böngésző eleve nem futtat benne scriptet / nem tölt külső
 * erőforrást), ez a takarítás védőháló: script, esemény-attribútumok, külső
 * hivatkozások és a `<foreignObject>` eltávolítása.
 */
class SvgSanitizer
{
    public static function clean(string $svg): ?string
    {
        $svg = trim($svg);

        if (! str_contains(strtolower($svg), '<svg')) {
            return null;
        }

        $patterns = [
            '#<\?xml[^>]*\?>#i' => '',
            '#<!DOCTYPE[^>]*>#i' => '',
            '#<!ENTITY[^>]*>#i' => '',
            '#<script\b[^>]*>.*?</script>#is' => '',
            '#<script\b[^>]*/?>#i' => '',
            '#<foreignObject\b[^>]*>.*?</foreignObject>#is' => '',
            '#\son\w+\s*=\s*"[^"]*"#i' => '',
            "#\son\w+\s*=\s*'[^']*'#i" => '',
            '#(href|xlink:href)\s*=\s*"\s*javascript:[^"]*"#i' => '',
            "#(href|xlink:href)\s*=\s*'\s*javascript:[^']*'#i" => '',
        ];

        $clean = preg_replace(array_keys($patterns), array_values($patterns), $svg);

        return is_string($clean) && str_contains(strtolower($clean), '<svg') ? trim($clean) : null;
    }
}
