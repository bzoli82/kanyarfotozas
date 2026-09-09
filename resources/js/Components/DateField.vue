<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from '@/Composables/useI18n';

/**
 * Az oldal stílusába illő dátumválasztó a natív <input type="date"> helyett
 * (annak a legördülő naptára OS-chrome, nem lehet stílusozni). HU: hétfő-kezdés.
 * Érték: 'YYYY-MM-DD' string (vagy '' ha nincs).
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    dense: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const { t, locale } = useI18n();

const intlLocale = computed(() => (locale.value === 'en' ? 'en-GB' : 'hu-HU'));
const open = ref(false);
const root = ref(null);

const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const todayIso = () => iso(new Date());

const viewDate = ref(startOfMonth(props.modelValue ? new Date(props.modelValue) : new Date()));
watch(
    () => props.modelValue,
    (v) => {
        if (v) viewDate.value = startOfMonth(new Date(v));
    },
);

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(intlLocale.value, { month: 'long', year: 'numeric' }).format(viewDate.value),
);

const weekdays = computed(() => {
    const fmt = new Intl.DateTimeFormat(intlLocale.value, { weekday: 'short' });
    // 2024-01-01 hétfő volt → hétfő-kezdésű fejléc
    return [1, 2, 3, 4, 5, 6, 7].map((day) => fmt.format(new Date(2024, 0, day)));
});

const days = computed(() => {
    const first = startOfMonth(viewDate.value);
    const offset = (first.getDay() + 6) % 7;
    const gridStart = new Date(first);
    gridStart.setDate(first.getDate() - offset);
    const out = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const key = iso(d);
        out.push({
            key,
            label: d.getDate(),
            inMonth: d.getMonth() === viewDate.value.getMonth(),
            today: key === todayIso(),
            selected: props.modelValue === key,
        });
    }
    return out;
});

const triggerLabel = computed(() => {
    if (!props.modelValue) return props.placeholder;
    return new Intl.DateTimeFormat(intlLocale.value, { year: 'numeric', month: 'short', day: 'numeric' }).format(
        new Date(props.modelValue),
    );
});

function shiftMonth(delta) {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + delta, 1);
}
function pick(key) {
    emit('update:modelValue', key);
    close();
}
function clear() {
    emit('update:modelValue', '');
    close();
}
function goToday() {
    viewDate.value = startOfMonth(new Date());
    emit('update:modelValue', todayIso());
    close();
}

function onOutside(e) {
    if (root.value && !root.value.contains(e.target)) close();
}
function toggle() {
    open.value ? close() : openMenu();
}
function openMenu() {
    open.value = true;
    if (props.modelValue) viewDate.value = startOfMonth(new Date(props.modelValue));
    document.addEventListener('mousedown', onOutside);
}
function close() {
    open.value = false;
    document.removeEventListener('mousedown', onOutside);
}
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-[var(--radius-base)] border bg-surface-2 text-left hover:border-accent focus:outline-none"
            :class="[
                modelValue ? 'border-accent text-accent' : 'border-border text-content',
                dense ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-2.5 text-sm',
            ]"
            :aria-expanded="open"
            @click="toggle"
        >
            <span class="flex items-center gap-1.5 truncate" :class="{ 'text-muted': !modelValue }">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                {{ triggerLabel }}
            </span>
            <svg
                width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                class="ml-1.5 shrink-0 transition-transform" :class="{ 'rotate-180': open }"
            ><path d="m6 9 6 6 6-6" /></svg>
        </button>

        <div
            v-if="open"
            class="absolute left-0 top-full z-40 mt-1 w-[16rem] rounded-[var(--radius-base)] border border-border bg-surface-2 p-3 shadow-2xl shadow-black/40"
        >
            <div class="flex items-center justify-between">
                <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="shiftMonth(-1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                </button>
                <span class="text-xs font-semibold capitalize text-content">{{ monthLabel }}</span>
                <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="shiftMonth(1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" /></svg>
                </button>
            </div>

            <div class="mt-2 grid grid-cols-7 gap-0.5 text-center">
                <span v-for="wd in weekdays" :key="wd" class="py-1 text-[10px] font-semibold uppercase text-muted">{{ wd }}</span>
                <button
                    v-for="d in days"
                    :key="d.key"
                    type="button"
                    class="aspect-square rounded-md text-xs transition-colors"
                    :class="[
                        d.selected
                            ? 'bg-accent font-semibold text-white'
                            : d.inMonth
                              ? 'text-content hover:bg-surface-1'
                              : 'text-muted/50 hover:bg-surface-1',
                        d.today && !d.selected ? 'ring-1 ring-inset ring-accent' : '',
                    ]"
                    @click="pick(d.key)"
                >
                    {{ d.label }}
                </button>
            </div>

            <div class="mt-2 flex items-center justify-between border-t border-border pt-2 text-[11px]">
                <button type="button" class="font-semibold text-accent hover:text-accent-hover" @click="goToday">
                    {{ locale === 'en' ? 'Today' : 'Ma' }}
                </button>
                <button v-if="modelValue" type="button" class="text-muted underline hover:text-content" @click="clear">
                    {{ t('home.search.clear') }}
                </button>
            </div>
        </div>
    </div>
</template>
