<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    images: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const filter = ref('all');
const selected = ref(new Set());

const FILTERS = [
    { key: 'all', label: 'Mind' },
    { key: 'jpg', label: 'JPG' },
    { key: 'png', label: 'PNG' },
    { key: 'webp', label: 'WebP' },
    { key: 'svg', label: 'SVG' },
    { key: 'orphan', label: 'Használatlan' },
    { key: 'big', label: 'Nagy méret' },
];

const shown = computed(() => {
    return props.images.filter((img) => {
        if (filter.value === 'all') return true;
        if (filter.value === 'orphan') return !img.used;
        if (filter.value === 'big') return img.big;
        if (filter.value === 'jpg') return img.format === 'jpg';
        return img.format === filter.value;
    });
});

function toggle(key) {
    const s = new Set(selected.value);
    s.has(key) ? s.delete(key) : s.add(key);
    selected.value = s;
}

function selectAllShown() {
    selected.value = new Set(shown.value.map((i) => i.key));
}

function clearSelection() {
    selected.value = new Set();
}

const selectedImages = computed(() => props.images.filter((i) => selected.value.has(i.key)));
const selectedConvertible = computed(() => selectedImages.value.filter((i) => i.convertible));
const selectedOrphans = computed(() => selectedImages.value.filter((i) => !i.used));

function fmtBytes(b) {
    if (!b) return '—';
    if (b < 1024) return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(0) + ' KB';
    return (b / 1024 / 1024).toFixed(1) + ' MB';
}

const convertForm = useForm({ keys: [], quality: 82, max_width: null });

function runConvert() {
    convertForm.keys = selectedConvertible.value.map((i) => i.key);
    if (convertForm.keys.length === 0) return;
    convertForm.post('/admin/settings/images/convert', {
        preserveScroll: true,
        onSuccess: () => clearSelection(),
    });
}

const deleteForm = useForm({ keys: [] });

function runDelete() {
    const keys = selectedOrphans.value.map((i) => i.key);
    if (keys.length === 0) return;
    if (!confirm(`${keys.length} használatlan fájl végleges törlése?`)) return;
    deleteForm.keys = keys;
    deleteForm.post('/admin/settings/images/delete', {
        preserveScroll: true,
        onSuccess: () => clearSelection(),
    });
}

function reload() {
    router.reload({ only: ['images', 'summary'] });
}

const FORMAT_BADGE = {
    jpg: 'bg-amber-500/15 text-amber-500',
    png: 'bg-sky-500/15 text-sky-400',
    webp: 'bg-emerald-500/15 text-emerald-400',
    svg: 'bg-violet-500/15 text-violet-400',
    gif: 'bg-pink-500/15 text-pink-400',
};
</script>

<template>
    <Head title="Képtár" />

    <AdminLayout>
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Képtár</h1>
                <p class="mt-1 max-w-2xl text-sm text-muted">
                    Az <strong class="text-content">oldal-képek</strong> (hero, OG megosztókép, logó, profilképek) egy helyen.
                    A médiát, a vízjeles előnézeteket és a letölthető fájlokat <strong class="text-content">nem</strong> tartalmazza.
                    A JPG/PNG képeket WebP-re konvertálhatod (a rendszer a hivatkozásokat is átírja), a használatlan fájlokat törölheted.
                </p>
            </div>
            <button type="button" class="shrink-0 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted hover:text-content" @click="reload">
                Frissítés
            </button>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3">
                <div class="text-lg font-bold text-content">{{ summary.total }}</div>
                <div class="text-[11px] uppercase tracking-wide text-muted">összes kép</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3">
                <div class="text-lg font-bold text-content">{{ summary.orphans }}</div>
                <div class="text-[11px] uppercase tracking-wide text-muted">használatlan</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3">
                <div class="text-lg font-bold text-content">{{ summary.convertible }}</div>
                <div class="text-[11px] uppercase tracking-wide text-muted">konvertálható</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3">
                <div class="text-lg font-bold text-content">{{ summary.big }}</div>
                <div class="text-[11px] uppercase tracking-wide text-muted">nagy méretű (&gt;500 KB)</div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <button
                v-for="f in FILTERS"
                :key="f.key"
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                :class="filter === f.key ? 'border-accent bg-accent text-white' : 'border-border text-muted hover:text-content'"
                @click="filter = f.key"
            >
                {{ f.label }}
            </button>
        </div>

        <div v-if="selected.size > 0" class="sticky top-2 z-10 mt-4 flex flex-wrap items-center gap-3 rounded-[var(--radius-base)] border border-accent bg-surface-1 px-4 py-3 shadow-lg">
            <span class="text-sm font-semibold text-content">{{ selected.size }} kijelölve</span>
            <span class="text-xs text-muted">({{ selectedConvertible.length }} konvertálható · {{ selectedOrphans.length }} használatlan)</span>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="text-xs text-muted hover:text-content" @click="clearSelection">Kijelölés törlése</button>
                <button
                    type="button"
                    :disabled="selectedOrphans.length === 0 || deleteForm.processing"
                    class="rounded-lg border border-red-500/50 px-3 py-1.5 text-xs font-semibold text-red-400 hover:bg-red-500/10 disabled:opacity-40"
                    @click="runDelete"
                >
                    Törlés ({{ selectedOrphans.length }})
                </button>
            </div>
        </div>

        <div v-if="selectedConvertible.length > 0" class="mt-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
            <h2 class="text-sm font-semibold text-content">Csoportos WebP-konvertálás — {{ selectedConvertible.length }} kép</h2>
            <div class="mt-3 flex flex-wrap items-end gap-4">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Minőség (40–100)</span>
                    <input v-model.number="convertForm.quality" type="number" min="40" max="100" class="w-24 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Max. szélesség (px, opcionális)</span>
                    <input v-model.number="convertForm.max_width" type="number" min="200" max="4000" placeholder="változatlan" class="w-40 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <button
                    type="button"
                    :disabled="convertForm.processing"
                    class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    @click="runConvert"
                >
                    Konvertálás WebP-re
                </button>
            </div>
            <p class="mt-2 text-[11px] text-muted">
                A régi fájl megmarad, de <strong>használatlanná</strong> válik — a „Használatlan" szűrőben törölheted, ha meggyőződtél az eredményről.
            </p>
        </div>

        <p v-if="shown.length === 0" class="mt-8 text-sm text-muted">Nincs ide illő kép.</p>

        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="img in shown"
                :key="img.key"
                class="overflow-hidden rounded-[var(--radius-base)] border transition"
                :class="selected.has(img.key) ? 'border-accent ring-1 ring-accent' : 'border-border'"
            >
                <label class="relative block cursor-pointer bg-[repeating-conic-gradient(#0000000d_0_25%,transparent_0_50%)] [background-size:16px_16px]">
                    <input type="checkbox" class="absolute left-2 top-2 z-10 h-4 w-4 accent-[var(--color-accent)]" :checked="selected.has(img.key)" @change="toggle(img.key)" />
                    <img :src="img.url" :alt="img.key" loading="lazy" class="h-36 w-full object-contain" />
                </label>
                <div class="space-y-1.5 bg-surface-1 p-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="FORMAT_BADGE[img.format] || 'bg-surface-2 text-muted'">{{ img.format }}</span>
                        <span class="text-[11px] text-muted">{{ fmtBytes(img.bytes) }}</span>
                        <span v-if="img.width" class="text-[11px] text-muted">{{ img.width }}×{{ img.height }}</span>
                        <span v-if="img.big" class="ml-auto text-[11px] font-semibold text-amber-500" title="Nagy fájlméret — érdemes konvertálni/kicsinyíteni">nagy</span>
                    </div>
                    <div class="truncate text-[11px] text-muted" :title="img.key">{{ img.key }}</div>
                    <div v-if="img.used" class="flex flex-wrap gap-1">
                        <a
                            v-for="(u, i) in img.used_by"
                            :key="i"
                            :href="u.href"
                            class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] text-content hover:text-accent"
                        >{{ u.label }}</a>
                    </div>
                    <div v-else class="text-[10px] font-semibold uppercase tracking-wide text-muted">használatlan</div>
                </div>
            </div>
        </div>

        <div v-if="shown.length > 0" class="mt-4 flex gap-3 text-xs">
            <button type="button" class="text-muted hover:text-content" @click="selectAllShown">Összes látható kijelölése</button>
            <button v-if="selected.size" type="button" class="text-muted hover:text-content" @click="clearSelection">Kijelölés törlése</button>
        </div>
    </AdminLayout>
</template>
