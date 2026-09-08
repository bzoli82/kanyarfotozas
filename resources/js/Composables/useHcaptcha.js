import { onBeforeUnmount, ref, watch } from 'vue';

const SCRIPT_URL = 'https://js.hcaptcha.com/1/api.js?render=explicit';
let scriptPromise = null;

function loadScript() {
    if (typeof window !== 'undefined' && window.hcaptcha) return Promise.resolve(window.hcaptcha);
    if (scriptPromise) return scriptPromise;

    scriptPromise = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = SCRIPT_URL;
        s.async = true;
        s.defer = true;
        s.onload = () => resolve(window.hcaptcha);
        s.onerror = () => reject(new Error('hCaptcha script betöltése sikertelen'));
        document.head.appendChild(s);
    });

    return scriptPromise;
}

/**
 * hCaptcha widget kezelése (App\Services\CaptchaSettings). Csak akkor tölt be
 * bármit, ha a superadmin bekapcsolta és van site key.
 *
 * @param {() => ({enabled: boolean, site_key: string}|null)} getConfig
 */
export function useHcaptcha(getConfig) {
    const token = ref('');
    const el = ref(null);
    const error = ref(false);
    let widgetId = null;

    async function render() {
        const cfg = getConfig();
        token.value = '';
        error.value = false;
        if (!cfg?.enabled || !cfg.site_key || !el.value) return;

        try {
            const hc = await loadScript();
            if (widgetId !== null) {
                hc.reset(widgetId);
                return;
            }
            widgetId = hc.render(el.value, {
                sitekey: cfg.site_key,
                callback: (t) => { token.value = t; },
                'expired-callback': () => { token.value = ''; },
                'error-callback': () => { token.value = ''; error.value = true; },
            });
        } catch {
            error.value = true;
        }
    }

    function reset() {
        token.value = '';
        if (widgetId !== null && window.hcaptcha) {
            try { window.hcaptcha.reset(widgetId); } catch { /* noop */ }
        }
    }

    watch(el, render);
    watch(getConfig, () => { widgetId = null; render(); });

    onBeforeUnmount(() => {
        if (widgetId !== null && window.hcaptcha) {
            try { window.hcaptcha.remove(widgetId); } catch { /* noop */ }
        }
    });

    return {
        token,
        el,
        error,
        reset,
        isEnabled: () => Boolean(getConfig()?.enabled && getConfig()?.site_key),
    };
}
