<script setup>
import { useThemeStore } from '@/Stores/theme';

const theme = useThemeStore();

const options = [
    { value: 'light', label: 'Világos' },
    { value: 'dark', label: 'Sötét' },
    { value: 'system', label: 'Rendszer' },
];
</script>

<template>
    <div class="inline-flex items-center gap-0.5 rounded-lg border border-border bg-surface-1 p-0.5" role="radiogroup" aria-label="Megjelenés">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="theme.mode === option.value"
            :title="option.label"
            class="grid h-7 w-7 place-items-center rounded-md text-content/70 transition-colors hover:text-content"
            :class="{ 'bg-surface-2 text-accent': theme.mode === option.value }"
            @click="theme.setMode(option.value)"
        >
            <svg v-if="option.value === 'light'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <svg v-else-if="option.value === 'dark'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="13" rx="2" />
                <path d="M8 21h8M12 17v4" />
            </svg>
        </button>
    </div>
</template>
