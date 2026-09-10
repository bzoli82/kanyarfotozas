<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useMediaUrl } from '@/Composables/useMediaUrl';

/**
 * Márkajel — a /admin/settings/branding-ból (Inertia shared `branding` prop).
 * Ha van feltöltött logó (kép), azt jeleníti meg <img>-ként; különben a
 * kétszínű szöveges logót (`logo_lead` + akcent `logo_tail`).
 *
 * `variant`:
 *  - 'auto'  (alap) — a <html data-theme> szerint választ világos/sötét logót
 *  - 'light' — világos háttérre szánt (sötét rajzú) logó
 *  - 'dark'  — sötét háttérre szánt (világos rajzú) logó, pl. a hero-fejléc
 */
const props = defineProps({
    variant: { type: String, default: 'auto' },
});

const page = usePage();
const { mediaUrl } = useMediaUrl();

const branding = computed(() => page.props.branding ?? { logo_lead: 'ROADSIDE', logo_tail: 'PHOTO', logo: null, logo_dark: null });

const wantDark = computed(() => {
    if (props.variant === 'dark') return true;
    if (props.variant === 'light') return false;
    if (typeof document === 'undefined') return true;

    return document.documentElement.dataset.theme !== 'light';
});

const logoSrc = computed(() => {
    const b = branding.value;
    const primary = b.logo ? mediaUrl(b.logo) : null;
    const dark = b.logo_dark ? mediaUrl(b.logo_dark) : null;

    if (wantDark.value) return dark || primary;

    return primary || dark;
});
</script>

<template>
    <img
        v-if="logoSrc"
        :src="logoSrc"
        :alt="branding.name"
        class="inline-block h-[1.7em] w-auto max-w-[200px] object-contain align-middle"
    />
    <span v-else>{{ branding.logo_lead }}<span v-if="branding.logo_tail" class="text-accent">{{ branding.logo_tail }}</span></span>
</template>
