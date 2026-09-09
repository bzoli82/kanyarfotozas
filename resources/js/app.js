import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { useThemeStore } from '@/Stores/theme';
import reveal from '@/directives/reveal';
import tilt from '@/directives/tilt';
import imgfade from '@/directives/imgfade';

/**
 * Elavult build utáni chunk-hiba kezelése. Ha a felhasználó tabja nyitva marad
 * egy deploy alatt, egy kliens-oldali dinamikus import (pl. a térkép Leaflet
 * chunkja, egy lazy Inertia oldal) a már törölt régi hash-elt fájlt kérné → 404.
 * Ilyenkor egyszer újratöltjük az oldalt a friss manifesttel. A sessionStorage
 * jelző véd a végtelen reload-ciklustól, ha az újratöltés után is hiányzik chunk.
 */
window.addEventListener('vite:preloadError', () => {
    if (window.__kfChunkReloading) {
        return;
    }
    try {
        if (sessionStorage.getItem('kf.chunkReloaded') === '1') {
            return;
        }
        sessionStorage.setItem('kf.chunkReloaded', '1');
    } catch {
        /* privát böngészés / letiltott storage — reload attól még mehet */
    }
    window.__kfChunkReloading = true;
    window.location.reload();
});

window.addEventListener('load', () => {
    setTimeout(() => {
        try {
            sessionStorage.removeItem('kf.chunkReloaded');
        } catch {
            /* noop */
        }
    }, 5000);
});

// Az oldal neve a szerverről jön (Inertia shared `branding` prop, /admin/settings/branding).
let brandName = import.meta.env.VITE_APP_NAME || 'RoadsidePhoto';

createInertiaApp({
    title: (title) => (title ? `${title} — ${brandName}` : brandName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        brandName = props.initialPage?.props?.branding?.name || brandName;

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia());

        app.directive('reveal', reveal);
        app.directive('tilt', tilt);
        app.directive('imgfade', imgfade);

        app.mount(el);

        useThemeStore().init(props.initialPage?.props?.themeMode);
    },
    progress: {
        // A blade <style> az app.js elott fut, igy a --color-accent mar elerheto —
        // a folyamatjelzo szine a temahoz igazodik.
        color:
            getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim() ||
            '#e63946',
    },
});
