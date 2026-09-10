<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';

const page = usePage();
const targets = computed(() => page.props.adminSearch ?? []);

const open = ref(false);
const query = ref('');
const active = ref(0);
const inputEl = ref(null);
const listEl = ref(null);

/** Ékezet- és kisbetű-független összehasonlításhoz. */
function fold(s) {
    return (s || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');
}

const results = computed(() => {
    const q = fold(query.value.trim());
    if (!q) return targets.value.slice(0, 8);

    const words = q.split(/\s+/).filter(Boolean);

    return targets.value
        .map((t) => {
            const hay = fold(`${t.label} ${t.section} ${t.hint} ${t.keywords || ''}`);
            const labelHay = fold(t.label);
            if (!words.every((w) => hay.includes(w))) return null;

            // Rangsor: címben szereplő találat előrébb, majd a szó eleji egyezés.
            let score = 0;
            if (words.every((w) => labelHay.includes(w))) score += 100;
            if (labelHay.startsWith(words[0])) score += 50;
            score -= t.label.length * 0.1;

            return { t, score };
        })
        .filter(Boolean)
        .sort((a, b) => b.score - a.score)
        .slice(0, 12)
        .map((r) => r.t);
});

watch(results, () => { active.value = 0; });

function show() {
    open.value = true;
    query.value = '';
    active.value = 0;
    nextTick(() => inputEl.value?.focus());
}

function hide() {
    open.value = false;
}

function go(target) {
    hide();
    router.visit(target.href);
}

function onKeydown(e) {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value ? hide() : show();

        return;
    }
    if (!open.value) return;

    if (e.key === 'Escape') {
        hide();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
        scrollActiveIntoView();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
        scrollActiveIntoView();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const t = results.value[active.value];
        if (t) go(t);
    }
}

function scrollActiveIntoView() {
    nextTick(() => {
        listEl.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    });
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

defineExpose({ show });
</script>

<template>
    <Teleport to="body">
        <Transition name="cmdp">
            <div
                v-if="open"
                class="fixed inset-0 z-[70] flex items-start justify-center bg-black/50 p-4 pt-[12vh]"
                @click.self="hide"
            >
                <div class="cmdp-panel w-full max-w-lg overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1 shadow-2xl" role="dialog" aria-modal="true" aria-label="Keresés a menüben">
                    <div class="flex items-center gap-2 border-b border-border px-4">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-muted"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                        <input
                            ref="inputEl"
                            v-model="query"
                            type="text"
                            placeholder="Pl. stripe, vízjel, visszatérítés, 2fa, animáció…"
                            class="w-full bg-transparent py-3.5 text-sm text-content placeholder:text-muted focus:outline-none"
                        />
                        <kbd class="shrink-0 rounded border border-border px-1.5 py-0.5 text-[10px] text-muted">Esc</kbd>
                    </div>

                    <div v-if="results.length === 0" class="px-4 py-8 text-center text-sm text-muted">
                        Nincs találat erre: „{{ query }}"
                    </div>

                    <ul v-else ref="listEl" class="max-h-[50vh] overflow-y-auto py-1.5">
                        <li v-for="(t, i) in results" :key="t.href + t.label">
                            <button
                                type="button"
                                :data-active="i === active"
                                class="flex w-full items-start gap-3 px-4 py-2.5 text-left"
                                :class="i === active ? 'bg-surface-2' : 'hover:bg-surface-2/60'"
                                @mouseenter="active = i"
                                @click="go(t)"
                            >
                                <AdminNavIcon :name="t.icon || 'grid'" class="mt-0.5 shrink-0 text-muted" />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-content">{{ t.label }}</span>
                                    <span class="block truncate text-[11px] text-muted">{{ t.hint }}</span>
                                </span>
                                <span class="mt-0.5 shrink-0 text-[10px] uppercase tracking-wide text-muted/70">{{ t.section }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="flex items-center gap-3 border-t border-border px-4 py-2 text-[10px] text-muted">
                        <span><kbd class="rounded border border-border px-1">↑</kbd><kbd class="ml-0.5 rounded border border-border px-1">↓</kbd> lépkedés</span>
                        <span><kbd class="rounded border border-border px-1">↵</kbd> megnyitás</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cmdp-enter-active,
.cmdp-leave-active {
    transition: opacity 150ms ease;
}
.cmdp-enter-from,
.cmdp-leave-to {
    opacity: 0;
}
.cmdp-enter-active .cmdp-panel {
    transition: transform 150ms ease;
}
.cmdp-enter-from .cmdp-panel {
    transform: translateY(-8px);
}
@media (prefers-reduced-motion: reduce) {
    .cmdp-enter-active,
    .cmdp-leave-active,
    .cmdp-enter-active .cmdp-panel {
        transition: none;
    }
}
</style>
