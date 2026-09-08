import { usePage } from '@inertiajs/vue3';

/**
 * A publikus média (thumbnail, vízjeles előnézet, HLS, sprite, hero) böngészőből
 * elérhető URL-je. A bázist a `HandleInertiaRequests` osztja meg `mediaBaseUrl`
 * propként: dev alatt `/storage`, élesben a Cloudflare R2 publikus domain.
 *
 * Használat SFC-ben:  `const { mediaUrl } = useMediaUrl()`  majd  `mediaUrl(key)`.
 * A `mediaUrl` önmagában is importálható statikus kontextusban (pl. store),
 * de csak az Inertia app inicializálása után hív.
 */
export function mediaUrl(key) {
    if (!key) {
        return '';
    }

    const base = usePage().props?.mediaBaseUrl ?? '/storage';

    return `${base}/${String(key).replace(/^\/+/, '')}`;
}

/**
 * Abszolút URL (og:image meta, megosztás) — relatív bázisnál az origin elé kerül.
 */
export function mediaUrlAbsolute(key) {
    const url = mediaUrl(key);

    if (!url || /^https?:\/\//.test(url)) {
        return url;
    }

    return `${window.location.origin}${url}`;
}

export function useMediaUrl() {
    return { mediaUrl, mediaUrlAbsolute };
}
