<script setup>
import { computed, ref } from 'vue';
import { useMediaUpload } from '@/Composables/useMediaUpload';

const props = defineProps({
    eventId: { type: [Number, String], required: true },
    isAdmin: { type: Boolean, default: false },
    photographers: { type: Array, default: () => [] },
    // Fotós esetén a saját almappája (csak jelzésként); adminnál null.
    scope: { type: String, default: null },
    // Támogatja-e a tároló a közvetlen (böngésző → R2) feltöltést.
    directUpload: { type: Boolean, default: false },
});

const emit = defineEmits(['import', 'refresh']);

const { jobs, phase, accept, start, reset } = useMediaUpload();

const fileInput = ref(null);
const dirInput = ref(null);
const dragOver = ref(false);
const photographerId = ref(props.photographers?.[0]?.id ?? '');
const result = ref(null);

const totalBytes = computed(() => jobs.reduce((s, j) => s + j.size, 0));
const doneCount = computed(() => jobs.filter((j) => j.status === 'done').length);
const errorCount = computed(() => jobs.filter((j) => j.status === 'error').length);
// Fájl-granularitású (nem bájt) — több ezer fájlnál is olcsó.
const overallProgress = computed(() => (jobs.length ? Math.round(((doneCount.value + errorCount.value) / jobs.length) * 100) : 0));

function humanSize(bytes) {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

function onPick(e) {
    const n = accept(e.target.files);
    if (!n) result.value = { error: 'A kijelölésben nincs kép vagy videó.' };
    e.target.value = '';
}

function onDrop(e) {
    dragOver.value = false;
    accept(e.dataTransfer.files);
}

async function upload() {
    if (props.isAdmin && !photographerId.value) {
        result.value = { error: 'Válassz fotóst.' };
        return;
    }
    result.value = null;
    const r = await start({ eventId: props.eventId, photographerId: photographerId.value });
    result.value = r;

    if (r.mode === 'direct' && r.uploaded > 0) {
        // Az onnan-importot az esemény oldala indítja (Inertia + folyamatjelző).
        emit('import', { photographerId: photographerId.value, paths: [r.importPath] });
    } else if (r.mode === 'app' && r.uploaded > 0) {
        emit('refresh');
    }
}

function clearAll() {
    reset();
    result.value = null;
}

defineExpose({ clearAll });
</script>

<template>
    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Média hozzáadása</h2>
        <p class="mt-1 text-xs text-muted">
            JPEG/PNG képek és MP4/MOV/AVI videók — <strong class="text-content">akár több ezer egyszerre</strong>
            (jelöld ki mindet, vagy válassz egy mappát). A feltöltés a háttérben, folytatható módon megy;
            a feldolgozás (thumbnail, vízjel) utána automatikusan elkészül.
            <template v-if="directUpload">A böngésző közvetlenül a felhőtárba tölt, ezért a szervert nem terheli.</template>
        </p>

        <label v-if="isAdmin" class="mt-4 block">
            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
            <select v-model="photographerId" class="rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </label>

        <!-- Drop-zone / választók -->
        <div
            v-if="phase === 'idle' && !jobs.length"
            class="mt-4 rounded-[var(--radius-base)] border-2 border-dashed px-4 py-10 text-center transition-colors"
            :class="dragOver ? 'border-accent bg-accent/5' : 'border-border'"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
        >
            <p class="text-sm text-muted">Húzd ide a fájlokat, vagy</p>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <button type="button" class="rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="fileInput.click()">
                    Fájlok kiválasztása
                </button>
                <button type="button" class="rounded-lg border border-border px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent" @click="dirInput.click()">
                    Mappa kiválasztása
                </button>
            </div>
            <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo,.mov,.avi" class="hidden" @change="onPick" />
            <input ref="dirInput" type="file" multiple webkitdirectory class="hidden" @change="onPick" />
        </div>

        <!-- Kijelölve, indítás előtt -->
        <div v-else-if="jobs.length && phase === 'idle'" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm">
            <span class="text-content"><strong>{{ jobs.length }}</strong> fájl kiválasztva · {{ humanSize(totalBytes) }}</span>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="clearAll">Mégse</button>
                <button type="button" class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="upload">Feltöltés indítása</button>
            </div>
        </div>

        <!-- Feltöltés / kész -->
        <div v-else-if="jobs.length" class="mt-4">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-content">
                    <template v-if="phase === 'uploading'">Feltöltés…</template>
                    <template v-else-if="phase === 'triggering'">Feltöltve — import indítása…</template>
                    <template v-else-if="phase === 'error'">Feltöltés hibákkal</template>
                    <template v-else>Feltöltés kész</template>
                </span>
                <span class="text-xs text-muted">
                    {{ doneCount }} / {{ jobs.length }}
                    <template v-if="errorCount">· <span class="text-accent">{{ errorCount }} hiba</span></template>
                </span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full bg-accent transition-all duration-300" :style="{ width: overallProgress + '%' }"></div>
            </div>

            <p v-if="result && result.mode === 'direct'" class="mt-2 text-xs text-muted">
                {{ result.uploaded }} fájl a felhőtárba töltve — a média fokozatosan megjelenik lent.
            </p>
            <p v-else-if="result && result.mode === 'app'" class="mt-2 text-xs text-muted">
                {{ result.uploaded }} fájl feltöltve, a feldolgozás elindult.
            </p>

            <ul v-if="errorCount" class="mt-2 max-h-32 space-y-0.5 overflow-y-auto text-xs text-accent">
                <li v-for="j in jobs.filter((x) => x.status === 'error')" :key="j.name">{{ j.name }} — {{ j.error }}</li>
            </ul>

            <button
                v-if="phase === 'done' || phase === 'error' || phase === 'triggering'"
                type="button"
                class="mt-3 rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted hover:text-content"
                @click="clearAll"
            >
                Új feltöltés
            </button>
        </div>

        <p v-if="result && result.error" class="mt-2 text-xs text-accent">{{ result.error }}</p>
    </div>
</template>
