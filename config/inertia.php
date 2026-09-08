<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Oldal-komponensek helye
    |--------------------------------------------------------------------------
    |
    | A projekt a `resources/js/Pages` mappát használja (NAGY „P"). A csomag
    | alapértelmezése `js/pages` (kis „p") — Windowson (case-insensitive) ez is
    | működik, de a CI Linuxon (case-sensitive) az `assertInertia()->component()`
    | létezés-ellenőrzése elhasal. Ezért itt explicit a helyes útvonal.
    |
    */

    'pages' => [

        'ensure_pages_exist' => true,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'js', 'jsx', 'ts', 'tsx', 'vue',
        ],

    ],

    'testing' => [

        'ensure_pages_exist' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | SSR
    |--------------------------------------------------------------------------
    |
    | A projekt NEM használ Inertia SSR-t éles bundle-lel (a szerver-oldali SEO
    | metát a View::composer('app') adja). Fejlesztésben a Vite dev-szerver
    | intézi automatikusan, ha kell.
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

    ],

];
