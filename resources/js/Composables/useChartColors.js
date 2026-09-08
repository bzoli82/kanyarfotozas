import { computed } from 'vue';
import { useThemeStore } from '@/Stores/theme';

/**
 * A Chart.js diagramok szinei a jelenlegi (admin altal testreszabhato accent +
 * vilagos/sotet mod) CSS custom property-kbol jonnek, hogy a diagramok mindig
 * illeszkedjenek a tema aktualis allapotahoz. A `theme.resolved`-re valo
 * reaktiv fuggoseg miatt tema-valtaskor a hasznalo komponens ujraszamolja.
 */
export function useChartColors() {
    const theme = useThemeStore();

    const colors = computed(() => {
        // eslint-disable-next-line no-unused-expressions
        theme.resolved; // reaktiv fuggoseg — a getComputedStyle nem magatol reaktiv
        const style = getComputedStyle(document.documentElement);
        const read = (name, fallback) => style.getPropertyValue(name).trim() || fallback;

        return {
            accent: read('--color-accent', '#e63946'),
            content: read('--color-content', '#f0f0f0'),
            muted: read('--color-muted', '#666666'),
            border: read('--color-border', '#2a2a2a'),
            surface1: read('--color-surface-1', '#141414'),
        };
    });

    const palette = computed(() => [
        colors.value.accent,
        '#3b82f6',
        '#22c55e',
        '#eab308',
        '#a855f7',
        '#06b6d4',
    ]);

    return { colors, palette };
}
