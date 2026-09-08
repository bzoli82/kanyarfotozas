<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    available: { type: Boolean, default: false },
    photographers: { type: Array, default: () => [] },
    // v-model:paths — a kijelölt fájl- ÉS mappa-útvonalak (a mappákat a szerver bontja ki)
    paths: { type: Array, default: () => [] },
    // v-model:photographerId — a kiválasztott fotós
    photographerId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['update:paths', 'update:photographerId']);

const open = ref(false);
const loading = ref(false);
const error = ref('');
const listing = ref(null);

async function load(path = '') {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch(`/admin/media-import/browse?path=${encodeURIComponent(path)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.available === false) {
            error.value = data.message;
            listing.value = null;
            return;
        }
        if (data.error) {
            error.value = data.error;
            return;
        }
        listing.value = data;
        emit('update:paths', []);
    } catch {
        error.value = 'Hiba a tároló böngészésekor.';
    } finally {
        loading.value = false;
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value && !listing.value && props.available) load('');
}

function togglePath(path) {
    emit(
        'update:paths',
        props.paths.includes(path) ? props.paths.filter((p) => p !== path) : [...props.paths, path],
    );
}

function selectAllFiles() {
    const all = (listing.value?.files ?? []).map((f) => f.path);
    const allSelected = all.length > 0 && all.every((p) => props.paths.includes(p));
    const rest = props.paths.filter((p) => !all.includes(p));
    emit('update:paths', allSelected ? rest : [...rest, ...all]);
}

const allFilesSelected = computed(() => {
    const all = (listing.value?.files ?? []).map((f) => f.path);
    return all.length > 0 && all.every((p) => props.paths.includes(p));
});

function fmtSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
}
</script>

<template>
    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Beolvasás tárolóból</h2>
            <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="toggle">
                {{ open ? 'Bezárás' : 'Megnyitás' }}
            </button>
        </div>
        <p class="mt-1 text-xs text-muted">
            A beállított tárolóból (SFTP / Cloudflare R2 „drop zone" / helyi mappa) importál teljes méretű képeket és videókat.
            Kijelölhetsz külön fájlokat <strong class="text-content">vagy egy egész mappát</strong> — a rendszer legyártja a
            thumbnailt, a vízjeles előnézetet és a letölthető változatokat, majd R2-be archivál. Sok fájlnál az import a
            háttérben fut.
        </p>

        <div v-if="open" class="mt-4">
            <p v-if="!available" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs text-muted">
                A tömeges import forrás-tároló nincs beállítva —
                <Link href="/admin/settings/storage" class="text-accent hover:text-accent-hover">Tárhely beállítások</Link>.
            </p>

            <template v-else>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                    <select
                        :value="photographerId"
                        class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"
                        @change="emit('update:photographerId', $event.target.value)"
                    >
                        <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>

                <div class="mt-3 flex flex-wrap items-center gap-1 text-xs text-muted">
                    <button type="button" class="hover:text-content" @click="load('')">gyökér</button>
                    <template v-for="seg in listing?.segments ?? []" :key="seg.path">
                        <span>/</span>
                        <button type="button" class="hover:text-content" @click="load(seg.path)">{{ seg.name }}</button>
                    </template>
                </div>

                <div class="mt-2 max-h-96 overflow-y-auto rounded-lg border border-border">
                    <p v-if="loading" class="px-3 py-4 text-xs text-muted">Betöltés…</p>
                    <p v-else-if="error" class="px-3 py-4 text-xs text-accent">{{ error }}</p>
                    <template v-else-if="listing">
                        <div
                            v-for="dir in listing.directories"
                            :key="dir.path"
                            class="flex items-center gap-2 border-b border-border px-3 py-2 text-sm hover:bg-surface-2"
                        >
                            <input
                                type="checkbox"
                                :checked="paths.includes(dir.path)"
                                class="accent-[var(--color-accent)]"
                                :title="`A teljes mappa importálása`"
                                @change="togglePath(dir.path)"
                            />
                            <button type="button" class="flex flex-1 items-center gap-2 truncate text-left text-content" @click="load(dir.path)">
                                <span aria-hidden="true">📁</span>
                                <span class="truncate">{{ dir.name }}</span>
                            </button>
                            <span v-if="dir.file_count != null" class="shrink-0 text-[11px] text-muted">~{{ dir.file_count }} fájl</span>
                        </div>
                        <label
                            v-for="file in listing.files"
                            :key="file.path"
                            class="flex cursor-pointer items-center gap-2 border-b border-border px-3 py-2 text-sm last:border-b-0 hover:bg-surface-2"
                        >
                            <input
                                type="checkbox"
                                :checked="paths.includes(file.path)"
                                class="accent-[var(--color-accent)]"
                                @change="togglePath(file.path)"
                            />
                            <span aria-hidden="true">🖼️</span>
                            <span class="flex-1 truncate text-content">{{ file.name }}</span>
                            <span class="shrink-0 text-[11px] text-muted">{{ fmtSize(file.size) }}</span>
                        </label>
                        <p v-if="listing.directories.length === 0 && listing.files.length === 0" class="px-3 py-4 text-xs text-muted">
                            Ez a mappa üres, vagy nincs benne kép/videó.
                        </p>
                    </template>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        :disabled="!listing || listing.files.length === 0"
                        class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content disabled:opacity-40"
                        @click="selectAllFiles"
                    >
                        {{ allFilesSelected ? 'Fájlkijelölés törlése' : 'Az összes fájl ebben a mappában' }}
                    </button>
                    <span class="text-xs text-muted">{{ paths.length }} tétel kijelölve (fájl / mappa)</span>
                </div>

                <slot name="action" :count="paths.length" />
            </template>
        </div>
    </div>
</template>
