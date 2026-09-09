@php($theme = app(\App\Services\ThemeSettings::class))
@php($palettes = $theme->resolvedPalettes())
@php($anim = app(\App\Services\AnimationSettings::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-anim="{{ $anim->enabled() ? 'on' : 'off' }}"
    data-anim-page="{{ $anim->enabled() ? $anim->pageTransition() : 'none' }}"
    data-anim-cards="{{ $anim->enabled() ? $anim->cards() : 'none' }}"
    data-anim-reveal="{{ $anim->enabled() && $anim->scrollReveal() ? 'on' : 'off' }}"
    data-anim-header="{{ $anim->enabled() && $anim->frostedHeader() ? 'on' : 'off' }}"
    data-anim-progress="{{ $anim->enabled() && $anim->progressBar() ? 'on' : 'off' }}"
    data-anim-imgfade="{{ $anim->enabled() && $anim->imageFade() ? 'on' : 'off' }}"
    data-anim-grain="{{ $anim->enabled() && $anim->heroGrain() ? 'on' : 'off' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ $seo['title'] ?? $brandName }}</title>

    {{-- SEO: az első lefestéshez és a JS-t nem futtató közösségi link-preview botokhoz.
         SPA-navigációnál az egyes oldalak <Head>-je / a `seo` shared prop frissíti. --}}
    @if (! empty($seo))
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        @if (! empty($seo['google_verification']))
            <meta name="google-site-verification" content="{{ $seo['google_verification'] }}">
        @endif
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:type" content="{{ $seo['type'] }}">
        <meta property="og:site_name" content="{{ $seo['site_name'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta property="og:locale" content="{{ $seo['locale'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image'] }}">
        @foreach ($seo['jsonld'] ?? [] as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    @endif

    {{-- Fotós mobil PWA (EPIC-15) --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="{{ $theme->accentColor() }}">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RoadsidePhoto">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
            });
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead

    {{-- Latogatoi sotet/vilagos/rendszer valasztas beallitasa lefestes elott, hogy ne villanjon fel a rossz tema (FOUC) --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('roadsidephoto.theme');
                var mode = stored || '{{ $theme->mode() }}';
                var resolved = mode === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
                    : mode;
                document.documentElement.setAttribute('data-theme', resolved);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    {{-- Superadmin altal testreszabott tema: sema (vilagos+sotet paletta) + akcentszin +
         sarok-lekerekites + betutipus. Ez a blokk az utolso a <head>-ben, azonos szelektorokkal
         mint a resources/css/app.css alapertelmezes -> forrassorrend alapjan mindig ez nyer.
         A latogatoi ThemeToggle a `data-theme`-mel valt vilagos/sotet kozott. --}}
    @php($cssVars = fn (array $p) => collect([
        '--color-surface-0' => $p['surface_0'], '--color-surface-1' => $p['surface_1'],
        '--color-surface-2' => $p['surface_2'], '--color-border' => $p['border'],
        '--color-content' => $p['content'], '--color-muted' => $p['muted'],
    ])->map(fn ($v, $k) => "$k: $v;")->implode(' '))
    <style>
        :root {
            --color-accent: {{ $theme->accentColor() }};
            --color-accent-hover: {{ $theme->accentHoverColor() }};
            --radius-base: {{ $theme->borderRadius() }}px;
            --font-sans-base: {{ $theme->fontStack() }};
            @foreach ($anim->resolvedVars() as $k => $v) {{ $k }}: {{ $v }}; @endforeach
        }
        :root, :root[data-theme='light'] { {{ $cssVars($palettes['light']) }} }
        :root[data-theme='dark'] { {{ $cssVars($palettes['dark']) }} }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme='light']) { {{ $cssVars($palettes['dark']) }} }
        }
    </style>
</head>
<body class="font-sans antialiased bg-surface-0 text-content">
    @inertia
</body>
</html>
