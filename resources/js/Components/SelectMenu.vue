<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * Natív <select> helyett egy CSS-sel teljesen stílusozható legördülő —
 * ugyanaz a lekerekített panel-megjelenés, mint az ország/helyszín kereső-mezőké
 * (a natív <select> popupja OS-chrome, nem lehet lekerekíteni).
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    // [{ value, label }]
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    // Akcent-keret, ha nem az alap érték van kiválasztva.
    active: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);

const selectedLabel = computed(() => {
    const found = props.options.find((o) => String(o.value) === String(props.modelValue));
    return found ? found.label : props.placeholder || props.options[0]?.label || '';
});

function onOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        close();
    }
}

function toggle() {
    open.value ? close() : openMenu();
}

function openMenu() {
    open.value = true;
    document.addEventListener('mousedown', onOutside);
}

function close() {
    open.value = false;
    document.removeEventListener('mousedown', onOutside);
}

function pick(value) {
    emit('update:modelValue', value);
    close();
}

onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-[var(--radius-base)] border bg-surface-2 text-left hover:border-accent focus:outline-none"
            :class="[
                active ? 'border-accent text-accent' : 'border-border text-content',
                dense ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-2.5 text-sm',
            ]"
            :aria-expanded="open"
            @click="toggle"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <svg
                width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                class="ml-1.5 shrink-0 transition-transform" :class="{ 'rotate-180': open }"
            ><path d="m6 9 6 6 6-6" /></svg>
        </button>

        <div
            v-if="open"
            class="absolute left-0 top-full z-30 mt-1 max-h-60 w-full min-w-[8rem] overflow-auto rounded-[var(--radius-base)] border border-border bg-surface-2 py-1 shadow-2xl shadow-black/40"
        >
            <button
                v-for="opt in options"
                :key="String(opt.value)"
                type="button"
                class="flex w-full items-center px-3 py-2 text-left text-sm hover:bg-surface-1"
                :class="String(opt.value) === String(modelValue) ? 'font-semibold text-accent' : 'text-content'"
                @click="pick(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>
    </div>
</template>
