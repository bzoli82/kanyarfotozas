<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from '@/Composables/useI18n';

/**
 * Dátumválasztó: kézzel is beírható (éééé-hh-nn), ÉS a naptár-ikonra kattintva
 * kinyíló, az oldal stílusába illő naptár. A naptár fejlécére kattintva
 * hónap- ill. évválasztó rács jön elő (nem kell hónapokat pörgetni).
 * Érték: 'YYYY-MM-DD' string (vagy '').
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    dense: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const { t, locale } = useI18n();
const intlLocale = computed(() => (locale.value === 'en' ? 'en-GB' : 'hu-HU'));

const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const todayIso = () => iso(new Date());

const open = ref(false);
const view = ref('days'); // days | months | years
const root = ref(null);
const text = ref(props.modelValue || '');
const viewDate = ref(startOfMonth(props.modelValue ? new Date(props.modelValue) : new Date()));
const decadeStart = ref(viewDate.value.getFullYear() - 6);

watch(
    () => props.modelValue,
    (v) => {
        text.value = v || '';
        if (v) viewDate.value = startOfMonth(new Date(v));
    },
);

function commitText() {
    const s = text.value.trim();
    if (s === '') {
        emit('update:modelValue', '');
        return;
    }
    const m = s.match(/^(\d{4})[-.\s/]+(\d{1,2})[-.\s/]+(\d{1,2})/);
    if (m) {
        const [y, mo, da] = [+m[1], +m[2], +m[3]];
        const d = new Date(y, mo - 1, da);
        if (d.getFullYear() === y && d.getMonth() === mo - 1 && d.getDate() === da) {
            emit('update:modelValue', iso(d));
            viewDate.value = startOfMonth(d);
        }
    }
}

// ─── nap-rács ─────────────────────────────────────────────
const monthYearLabel = computed(() =>
    new Intl.DateTimeFormat(intlLocale.value, { month: 'long', year: 'numeric' }).format(viewDate.value),
);
const weekdays = computed(() => {
    const fmt = new Intl.DateTimeFormat(intlLocale.value, { weekday: 'short' });
    return [1, 2, 3, 4, 5, 6, 7].map((day) => fmt.format(new Date(2024, 0, day)));
});
const days = computed(() => {
    const first = startOfMonth(viewDate.value);
    const offset = (first.getDay() + 6) % 7;
    const gridStart = new Date(first);
    gridStart.setDate(first.getDate() - offset);
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const key = iso(d);
        return {
            key,
            label: d.getDate(),
            inMonth: d.getMonth() === viewDate.value.getMonth(),
            today: key === todayIso(),
            selected: props.modelValue === key,
        };
    });
});

// ─── hónap-rács ───────────────────────────────────────────
const months = computed(() => {
    const fmt = new Intl.DateTimeFormat(intlLocale.value, { month: 'short' });
    return Array.from({ length: 12 }, (_, i) => ({ i, label: fmt.format(new Date(2024, i, 1)) }));
});

// ─── év-rács ──────────────────────────────────────────────
const years = computed(() => Array.from({ length: 12 }, (_, i) => decadeStart.value + i));

function shiftMonth(delta) {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + delta, 1);
}
function openMonths() {
    view.value = 'months';
}
function openYears() {
    decadeStart.value = viewDate.value.getFullYear() - 6;
    view.value = 'years';
}
function pickMonth(i) {
    viewDate.value = new Date(viewDate.value.getFullYear(), i, 1);
    view.value = 'days';
}
function pickYear(y) {
    viewDate.value = new Date(y, viewDate.value.getMonth(), 1);
    view.value = 'months';
}
function pickDay(key) {
    emit('update:modelValue', key);
    close();
}
function clear() {
    emit('update:modelValue', '');
    text.value = '';
    close();
}
function goToday() {
    emit('update:modelValue', todayIso());
    viewDate.value = startOfMonth(new Date());
    close();
}

function onOutside(e) {
    if (root.value && !root.value.contains(e.target)) close();
}
function openMenu() {
    if (open.value) return;
    open.value = true;
    view.value = 'days';
    if (props.modelValue) viewDate.value = startOfMonth(new Date(props.modelValue));
    document.addEventListener('mousedown', onOutside);
}
function toggle() {
    open.value ? close() : openMenu();
}
function close() {
    open.value = false;
    document.removeEventListener('mousedown', onOutside);
}
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative">
        <div
            class="flex items-center rounded-[var(--radius-base)] border bg-surface-2 transition-colors"
            :class="[modelValue ? 'border-accent' : 'border-border', open ? 'border-accent' : '']"
        >
            <input
                v-model="text"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :placeholder="placeholder || (locale === 'en' ? 'yyyy-mm-dd' : 'éééé-hh-nn')"
                class="w-full min-w-0 bg-transparent text-content placeholder:text-muted focus:outline-none"
                :class="[dense ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-2.5 text-sm', modelValue ? 'text-accent' : '']"
                @focus="openMenu"
                @input="commitText"
                @keydown.enter.prevent="commitText"
            />
            <button
                type="button"
                class="shrink-0 px-2 text-muted transition-colors hover:text-accent"
                :aria-label="locale === 'en' ? 'Open calendar' : 'Naptár megnyitása'"
                @click="toggle"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
            </button>
        </div>

        <div
            v-if="open"
            class="absolute left-0 top-full z-40 mt-1 w-[17rem] rounded-[var(--radius-base)] border border-border bg-surface-2 p-3 shadow-2xl shadow-black/40"
        >
            <!-- nap-nézet -->
            <template v-if="view === 'days'">
                <div class="flex items-center justify-between">
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="shiftMonth(-1)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold capitalize text-content hover:bg-surface-1" @click="openMonths">
                        {{ monthYearLabel }}
                    </button>
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
                        @click="pickDay(d.key)"
                    >
                        {{ d.label }}
                    </button>
                </div>
            </template>

            <!-- hónap-nézet -->
            <template v-else-if="view === 'months'">
                <div class="flex items-center justify-between">
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="viewDate = new Date(viewDate.getFullYear() - 1, viewDate.getMonth(), 1)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-content hover:bg-surface-1" @click="openYears">
                        {{ viewDate.getFullYear() }}
                    </button>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="viewDate = new Date(viewDate.getFullYear() + 1, viewDate.getMonth(), 1)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" /></svg>
                    </button>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-1">
                    <button
                        v-for="m in months"
                        :key="m.i"
                        type="button"
                        class="rounded-md py-2 text-xs capitalize transition-colors"
                        :class="m.i === viewDate.getMonth() ? 'bg-accent font-semibold text-white' : 'text-content hover:bg-surface-1'"
                        @click="pickMonth(m.i)"
                    >
                        {{ m.label }}
                    </button>
                </div>
            </template>

            <!-- év-nézet -->
            <template v-else>
                <div class="flex items-center justify-between">
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="decadeStart -= 12">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <span class="text-xs font-semibold text-content">{{ years[0] }}–{{ years[11] }}</span>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-1 hover:text-content" @click="decadeStart += 12">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" /></svg>
                    </button>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-1">
                    <button
                        v-for="y in years"
                        :key="y"
                        type="button"
                        class="rounded-md py-2 text-xs transition-colors"
                        :class="y === viewDate.getFullYear() ? 'bg-accent font-semibold text-white' : 'text-content hover:bg-surface-1'"
                        @click="pickYear(y)"
                    >
                        {{ y }}
                    </button>
                </div>
            </template>

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
