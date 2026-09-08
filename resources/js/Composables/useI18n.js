import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Konnyu i18n a HandleInertiaRequests altal megosztott `translations` propbol —
 * nincs kulon vue-i18n csomag. Hasznalat: `const { t, locale } = useI18n()`,
 * majd `t('nav.home')` vagy `t('cart.total', { count: 3 })`.
 */
export function useI18n() {
    const page = usePage();

    const locale = computed(() => page.props.locale ?? 'hu');

    function t(key, replacements = {}) {
        let value = page.props.translations?.[key] ?? key;

        for (const [k, v] of Object.entries(replacements)) {
            value = value.replace(new RegExp(`:${k}\\b`, 'g'), v);
        }

        return value;
    }

    return { t, locale };
}
