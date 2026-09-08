import { fileURLToPath, URL } from 'node:url';
import { copyFileSync, readdirSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

/**
 * A vite-plugin-pwa a public/build/ alá írja a sw.js + workbox-*.js + manifestet.
 * A service worker root scope-hoz a public/ gyökérből kell kiszolgálni, ezért
 * build után ide másoljuk (a precache URL-ek modifyURLPrefix-szel /build/-re
 * mutatnak, a ./workbox-* relatív import pedig a gyökérben megtalálja a párját).
 */
function copyPwaToPublicRoot() {
    const copy = () => {
        const from = 'public/build';
        const to = 'public';
        for (const file of readdirSync(from)) {
            if (file === 'sw.js' || file === 'manifest.webmanifest' || /^workbox-.*\.js$/.test(file)) {
                copyFileSync(`${from}/${file}`, `${to}/${file}`);
            }
        }
    };

    return {
        name: 'copy-pwa-to-public-root',
        apply: 'build',
        enforce: 'post',
        closeBundle: { sequential: true, order: 'post', handler: copy },
    };
}

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        // Fotós mobil PWA (EPIC-15). A sw.js + manifest a public/build alá kerül;
        // a Laravel /sw.js route root-scope-ban szolgálja ki (Service-Worker-Allowed: /).
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            outDir: 'public/build',
            buildBase: '/build/',
            manifestFilename: 'manifest.webmanifest',
            manifest: {
                name: 'KanyarFoto — Fotós',
                short_name: 'KanyarFoto',
                description: 'Kanyarfotózó események feltöltése mobilról.',
                start_url: '/upload',
                scope: '/',
                display: 'standalone',
                orientation: 'portrait',
                background_color: '#0d0d0d',
                theme_color: '#e63946',
                lang: 'hu',
                icons: [
                    { src: '/pwa-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/pwa-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,woff,woff2}'],
                modifyURLPrefix: { '': '/build/' },
                cleanupOutdatedCaches: true,
                // Az uj service worker azonnal atveszi az iranyitast a mar nyitott
                // tabok felett is (deploy utan ne ragadjon be a regi precache).
                clientsClaim: true,
                // navigateFallback szandekosan nincs: server-oldali Inertia app, a
                // teljes-oldal navigacio a szervert kell hogy talalja. Offline UX-et a
                // feltolto oldal sajat IndexedDB-sora + a precache-elt app shell ad.
                runtimeCaching: [
                    {
                        urlPattern: /\/storage\/.*/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'kf-media',
                            expiration: { maxEntries: 200, maxAgeSeconds: 604800 },
                        },
                    },
                ],
            },
        }),
        copyPwaToPublicRoot(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
