<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FtpImportBrowser from '@/Components/FtpImportBrowser.vue';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { mediaUrl } = useMediaUrl();

const props = defineProps({
    event: Object,
    media: Object,
    countries: { type: Array, default: () => [] },
    basePrice: { type: Number, default: 1490 },
    organizers: { type: Array, default: () => [] },
    photographers: Array,
    isAdmin: Boolean,
    canEditEvent: Boolean,
    ftpImport: { type: Object, default: null },
    videoMode: { type: String, default: 'pipeline' },
    watermarkText: { type: String, default: '' },
});

const preprocessedVideo = props.videoMode === 'preprocessed';

const ffmpegHint = `ffmpeg -i BEMENET.mp4 -vf "scale=-2:480,drawtext=text='${props.watermarkText}':fontcolor=white@0.5:fontsize=h/18:x=(w-tw)/2:y=(h-th)/2" -c:v libx264 -crf 30 -preset veryfast -c:a aac -b:a 96k BEMENET_lores.mp4`;

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

const statusLabel = {
    processing: 'Feldolgozás alatt',
    ready: 'Kész',
    failed: 'Hibás',
    hidden: 'Elrejtve',
};

const statusClass = {
    processing: 'border-border text-muted',
    ready: 'border-accent text-accent',
    failed: 'border-accent bg-accent/10 text-accent',
    hidden: 'border-border text-muted',
};

// --- Esemeny adatai (a korabbi kulon szerkeszto-oldal helyett itt, egy helyen) ---
const editForm = useForm({
    country_id: props.event.country_id ?? '',
    name: props.event.name ?? '',
    location: props.event.location ?? '',
    latitude: props.event.latitude ?? '',
    longitude: props.event.longitude ?? '',
    event_date: props.event.event_date?.slice(0, 10) ?? '',
    starts_at: props.event.starts_at?.slice(0, 16) ?? '',
    ends_at: props.event.ends_at?.slice(0, 16) ?? '',
    status: props.event.status ?? 'draft',
    photo_price_cents: props.event.photo_price_cents ?? props.basePrice,
    video_price_cents: props.event.video_price_cents ?? props.basePrice,
    organizer_id: props.event.organizer_id ?? '',
    organizer_share_percent: props.event.organizer_share_percent ?? null,
});

function saveEvent() {
    editForm.put(`/admin/events/${props.event.id}`, { preserveScroll: true });
}

function destroyEvent() {
    if (!confirm('Biztosan törlöd ezt az eseményt? Ez csak akkor sikerül, ha nincs hozzá feltöltött média.')) return;
    router.delete(`/admin/events/${props.event.id}`);
}

// --- Batch feltoltes ---
const fileInput = ref(null);
const uploadForm = useForm({
    photographer_id: props.photographers?.[0]?.id ?? '',
    files: [],
});

function onFilesSelected(e) {
    uploadForm.files = Array.from(e.target.files ?? []);
}

function submitUpload() {
    if (uploadForm.files.length === 0) return;

    uploadForm.post(`/admin/events/${props.event.id}/media`, {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('files');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

// --- Media soronkenti muveletek (lathatosag, torles) — az ar esemeny-szintu ---
function toggleVisibility(item) {
    const next = item.status === 'hidden' ? 'ready' : 'hidden';
    router.patch(`/admin/media/${item.id}`, { status: next }, { preserveScroll: true });
}

function destroyMedia(item) {
    if (!confirm('Biztosan törlöd ezt a médiát?')) return;
    router.delete(`/admin/media/${item.id}`, { preserveScroll: true });
}

// --- Tobbszoros kijeloles (drag / kattintas) + koteges torles ---
const gridRef = ref(null);
const selectedIds = ref([]);
const marquee = ref(null); // {x,y,w,h} viewport-koordinatakban a vizualis dobozhoz
const bulkForm = useForm({ ids: [], delete_ftp_source: false });

let dragStart = null;
let dragBase = [];
let dragMoved = false;

function isSelected(id) {
    return selectedIds.value.includes(id);
}

function toggleSelect(id) {
    selectedIds.value = isSelected(id)
        ? selectedIds.value.filter((x) => x !== id)
        : [...selectedIds.value, id];
}

function clearSelection() {
    selectedIds.value = [];
}

function onGridPointerDown(e) {
    if (e.button !== 0 || e.target.closest('button, a, input, label, select')) return;
    dragStart = { x: e.clientX, y: e.clientY };
    dragBase = e.shiftKey ? [...selectedIds.value] : [];
    dragMoved = false;
    marquee.value = null;
    window.addEventListener('pointermove', onGridPointerMove);
    window.addEventListener('pointerup', onGridPointerUp, { once: true });
}

function onGridPointerMove(e) {
    if (!dragStart) return;
    const x = Math.min(dragStart.x, e.clientX);
    const y = Math.min(dragStart.y, e.clientY);
    const w = Math.abs(e.clientX - dragStart.x);
    const h = Math.abs(e.clientY - dragStart.y);

    if (!dragMoved && w < 4 && h < 4) return;
    dragMoved = true;
    marquee.value = { x, y, w, h };
    document.body.style.userSelect = 'none';

    const box = { left: x, top: y, right: x + w, bottom: y + h };
    const hits = [];
    gridRef.value?.querySelectorAll('[data-media-id]').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.left < box.right && r.right > box.left && r.top < box.bottom && r.bottom > box.top) {
            hits.push(Number(el.dataset.mediaId));
        }
    });
    selectedIds.value = [...new Set([...dragBase, ...hits])];
}

function onGridPointerUp(e) {
    window.removeEventListener('pointermove', onGridPointerMove);
    document.body.style.userSelect = '';

    if (!dragMoved) {
        const card = e.target.closest('[data-media-id]');
        if (card) {
            toggleSelect(Number(card.dataset.mediaId));
        } else if (!e.target.closest('button, a, input, label, select')) {
            clearSelection();
        }
    }

    dragStart = null;
    marquee.value = null;
}

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', onGridPointerMove);
    document.body.style.userSelect = '';
});

const selectedHasFtpSource = computed(() =>
    props.media.data.some((m) => selectedIds.value.includes(m.id) && m.import_source_path),
);

function deleteSelected() {
    const n = selectedIds.value.length;
    if (n === 0) return;

    const alsoFtp = bulkForm.delete_ftp_source && selectedHasFtpSource.value;
    const msg = alsoFtp
        ? `Törlöd a kijelölt ${n} médiát ÉS az FTP-ről importált eredeti fájljukat is a távoli szerverről? Ez nem vonható vissza.`
        : `Törlöd a kijelölt ${n} médiát? Ez nem vonható vissza.`;
    if (!confirm(msg)) return;

    bulkForm
        .transform(() => ({ ids: selectedIds.value, delete_ftp_source: alsoFtp }))
        .post(`/admin/events/${props.event.id}/media/bulk-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                bulkForm.delete_ftp_source = false;
            },
        });
}

// --- Beolvasas FTP-rol (a bongeszot a FtpImportBrowser komponens adja) ---
const importForm = useForm({
    photographer_id: props.photographers?.[0]?.id ?? '',
    paths: [],
});

function runImport() {
    if (importForm.paths.length === 0) return;
    importForm.post(`/admin/events/${props.event.id}/import`, {
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset('paths');
            setTimeout(pollImportStatus, 800);
        },
    });
}

// --- Hatter-import folyamatjelzo ---
const importStatus = ref(null);
let importPollTimer = null;

async function pollImportStatus() {
    try {
        const res = await fetch(`/admin/events/${props.event.id}/import/status`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        const wasRunning = importStatus.value?.running;
        importStatus.value = data.total > 0 ? data : null;

        if (data.running) {
            importPollTimer = setTimeout(pollImportStatus, 2500);
        } else if (wasRunning && data.finished) {
            // Frissen befejeződött — töltsük újra a média-rácsot.
            router.reload({ only: ['media'] });
        }
    } catch {
        // csendben — a következő poll újrapróbál
    }
}

onMounted(pollImportStatus);
onBeforeUnmount(() => importPollTimer && clearTimeout(importPollTimer));
</script>

<template>
    <Head :title="event.name" />

    <AdminLayout>
        <div>
            <Link href="/admin/events" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">← Események</Link>
            <h1 class="font-display mt-1 text-xl font-bold uppercase tracking-tight text-content">{{ event.name }}</h1>
            <p class="text-sm text-muted">
                {{ event.country?.flag_emoji }} {{ event.country?.name_hu }} · {{ event.location }} · {{ event.event_date?.slice(0, 10) }}
            </p>
        </div>

        <!-- 1. Esemeny adatai -->
        <div v-if="canEditEvent" class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Esemény adatai</h2>

            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="saveEvent">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Esemény neve</span>
                    <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-accent">{{ editForm.errors.name }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Helyszín</span>
                    <input v-model="editForm.location" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.location" class="mt-1 text-xs text-accent">{{ editForm.errors.location }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Ország</span>
                    <select v-model="editForm.country_id" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="" disabled>Válassz országot</option>
                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.flag_emoji }} {{ c.name_hu }}</option>
                    </select>
                    <p v-if="editForm.errors.country_id" class="mt-1 text-xs text-accent">{{ editForm.errors.country_id }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Státusz</span>
                    <select v-model="editForm.status" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="draft">Vázlat</option>
                        <option value="announced">Meghirdetve</option>
                        <option value="live">Élő</option>
                        <option value="archived">Archivált</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szélesség (lat)</span>
                    <input v-model="editForm.latitude" type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.latitude" class="mt-1 text-xs text-accent">{{ editForm.errors.latitude }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Hosszúság (lon)</span>
                    <input v-model="editForm.longitude" type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.longitude" class="mt-1 text-xs text-accent">{{ editForm.errors.longitude }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátum</span>
                    <input v-model="editForm.event_date" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.event_date" class="mt-1 text-xs text-accent">{{ editForm.errors.event_date }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Kezdés</span>
                    <input v-model="editForm.starts_at" type="datetime-local" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.starts_at" class="mt-1 text-xs text-accent">{{ editForm.errors.starts_at }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Befejezés (opcionális)</span>
                    <input v-model="editForm.ends_at" type="datetime-local" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.ends_at" class="mt-1 text-xs text-accent">{{ editForm.errors.ends_at }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotó ára (Ft)</span>
                    <input v-model.number="editForm.photo_price_cents" type="number" min="0" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.photo_price_cents" class="mt-1 text-xs text-accent">{{ editForm.errors.photo_price_cents }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Videó ára (Ft)</span>
                    <input v-model.number="editForm.video_price_cents" type="number" min="0" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="editForm.errors.video_price_cents" class="mt-1 text-xs text-accent">{{ editForm.errors.video_price_cents }}</p>
                </label>

                <p class="text-xs text-muted sm:col-span-2">Az ár az esemény összes fotójára / összes videójára vonatkozik. Módosításkor a már feltöltött (de még meg nem vásárolt) médiák ára is átáll.</p>

                <template v-if="isAdmin && organizers.length">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szervező</span>
                        <select v-model="editForm.organizer_id" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                            <option value="">— nincs —</option>
                            <option v-for="o in organizers" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szervező részesedése (%)</span>
                        <input v-model.number="editForm.organizer_share_percent" type="number" min="0" max="100" :disabled="!editForm.organizer_id" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none disabled:opacity-50" />
                        <p v-if="editForm.errors.organizer_share_percent" class="mt-1 text-xs text-accent">{{ editForm.errors.organizer_share_percent }}</p>
                    </label>
                </template>

                <div class="flex flex-wrap items-center gap-3 pt-1 sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    >
                        {{ editForm.processing ? 'Mentés…' : 'Mentés' }}
                    </button>
                    <span v-if="editForm.recentlySuccessful" class="text-xs font-semibold uppercase tracking-wide text-accent">Elmentve ✓</span>
                </div>
            </form>

            <div class="mt-6 border-t border-border pt-5">
                <button
                    type="button"
                    class="w-full rounded-lg bg-red-600 px-6 py-3.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition-colors hover:bg-red-700 sm:w-auto"
                    @click="destroyEvent"
                >
                    Esemény törlése
                </button>
                <p class="mt-2 text-xs text-muted">Az eseményt csak akkor lehet törölni, ha nincs hozzá feltöltött média.</p>
            </div>
        </div>

        <!-- 2. Batch media feltoltes -->
        <div class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Média feltöltése</h2>
            <p class="mt-1 text-xs text-muted">
                JPEG/PNG képek és MP4/MOV/AVI videók, max. 200 fájl egyszerre. A feldolgozás (WebP, vízjel, sprite) a háttérben készül el — addig a feltöltött média „Feldolgozás alatt” státuszban marad.
            </p>

            <div v-if="preprocessedVideo" class="mt-3 rounded-[var(--radius-base)] border border-border bg-surface-2 p-3 text-xs text-muted">
                <p class="font-semibold text-content">Elő-feldolgozott videó mód</p>
                <p class="mt-1">
                    A szerver NEM kódol videót. Minden videóhoz tölts fel egy fájl-hármast közös alapnévvel:
                </p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li><code>klip.mp4</code> — teljes felbontású eredeti (ezt kapja meg a vásárló)</li>
                    <li><code>klip_lores.mp4</code> — kis felbontású, vízjelezett előnézet (kötelező)</li>
                    <li><code>klip.jpg</code> — állókép poszter (opcionális)</li>
                </ul>
                <p class="mt-2">Előnézet készítése helyben (a vízjel-szöveg az aktuális beállításból):</p>
                <pre class="mt-1 overflow-x-auto rounded bg-black/40 p-2 text-[11px] text-content">{{ ffmpegHint }}</pre>
            </div>

            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="submitUpload">
                <label v-if="isAdmin" class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                    <select v-model="uploadForm.photographer_id" class="rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>

                <label class="block flex-1">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fájlok</span>
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,video/mp4,video/quicktime,.mov,.avi"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content file:mr-3 file:rounded file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:text-white"
                        @change="onFilesSelected"
                    />
                </label>

                <button
                    type="submit"
                    :disabled="uploadForm.processing || uploadForm.files.length === 0"
                    class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                >
                    {{ uploadForm.processing ? 'Feltöltés…' : `Feltöltés (${uploadForm.files.length})` }}
                </button>
            </form>
            <p v-if="uploadForm.errors.files" class="mt-2 text-xs text-accent">{{ uploadForm.errors.files }}</p>
        </div>

        <!-- 3. Beolvasas FTP-rol -->
        <FtpImportBrowser
            v-if="ftpImport"
            class="mt-6"
            :available="ftpImport.available"
            :scope="ftpImport.scope"
            :photographers="photographers"
            v-model:paths="importForm.paths"
            v-model:photographer-id="importForm.photographer_id"
        >
            <template #action="{ count }">
                <div class="mt-3">
                    <button
                        type="button"
                        :disabled="importForm.processing || count === 0"
                        class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                        @click="runImport"
                    >
                        {{ importForm.processing ? 'Importálás…' : `Importálás (${count})` }}
                    </button>
                    <p v-if="importForm.errors.paths" class="mt-2 text-xs text-accent">{{ importForm.errors.paths }}</p>
                    <p v-if="importForm.errors.photographer_id" class="mt-2 text-xs text-accent">{{ importForm.errors.photographer_id }}</p>
                </div>
            </template>
        </FtpImportBrowser>

        <!-- Hatter-import folyamatjelzo -->
        <div v-if="importStatus" class="mt-4 rounded-[var(--radius-base)] border border-accent/40 bg-accent/5 p-4">
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="font-semibold text-content">
                    {{ importStatus.running ? 'Import folyamatban…' : 'Import kész' }}
                </span>
                <span class="text-xs text-muted">
                    {{ importStatus.processed }} / {{ importStatus.total }}
                    <template v-if="importStatus.skipped">· {{ importStatus.skipped }} kihagyva</template>
                    <template v-if="importStatus.failed">· {{ importStatus.failed }} hiba</template>
                </span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2">
                <div
                    class="h-full rounded-full bg-accent transition-all duration-500"
                    :style="{ width: Math.min(100, Math.round((importStatus.processed / Math.max(1, importStatus.total)) * 100)) + '%' }"
                ></div>
            </div>
            <p v-if="!importStatus.running" class="mt-2 text-xs text-muted">
                {{ importStatus.imported }} média importálva — a feldolgozás (thumbnail, vízjel) a háttérben fut, frissítsd az oldalt pár perc múlva.
            </p>
        </div>

        <!-- 4. Media grid -->
        <div class="mt-8">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Média ({{ media.total ?? media.data.length }})</h2>
                <button
                    v-if="media.data.length > 0"
                    type="button"
                    class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content"
                    @click="selectedIds = selectedIds.length === media.data.length ? [] : media.data.map((m) => m.id)"
                >
                    {{ selectedIds.length === media.data.length && media.data.length > 0 ? 'Kijelölés törlése' : 'Mind kijelöl' }}
                </button>
            </div>
            <p v-if="media.data.length > 0" class="mt-1 text-xs text-muted">
                Kattints egy elemre a kijelöléshez, vagy húzz egy keretet több elem köré. A kijelölteket egyszerre törölheted.
            </p>

            <!-- Koteges muvelet-sav -->
            <div
                v-if="selectedIds.length > 0"
                class="sticky top-2 z-20 mt-3 flex flex-wrap items-center gap-3 rounded-[var(--radius-base)] border border-accent bg-surface-1 px-4 py-2.5 shadow-lg"
            >
                <span class="text-sm font-semibold text-content">{{ selectedIds.length }} kijelölve</span>
                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="clearSelection">
                    Kijelölés törlése
                </button>
                <label v-if="selectedHasFtpSource" class="flex items-center gap-2 text-xs text-content">
                    <input v-model="bulkForm.delete_ftp_source" type="checkbox" class="accent-[var(--color-accent)]" />
                    Az FTP-ről importált eredeti fájlt is töröljem a szerverről
                </label>
                <button
                    type="button"
                    :disabled="bulkForm.processing"
                    class="ml-auto rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    @click="deleteSelected"
                >
                    {{ bulkForm.processing ? 'Törlés…' : `Törlés (${selectedIds.length})` }}
                </button>
            </div>

            <div ref="gridRef" class="relative mt-4 grid select-none gap-4 sm:grid-cols-2 lg:grid-cols-4" @pointerdown="onGridPointerDown">
                <div
                    v-for="item in media.data"
                    :key="item.id"
                    :data-media-id="item.id"
                    class="cursor-pointer overflow-hidden rounded-[var(--radius-base)] border bg-surface-1 transition-colors"
                    :class="isSelected(item.id) ? 'border-accent ring-2 ring-accent' : 'border-border'"
                >
                    <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-surface-2 text-xs text-muted">
                        <img
                            v-if="item.thumbnail_s3_key"
                            :src="mediaUrl(item.thumbnail_s3_key)"
                            :alt="item.photographer?.name"
                            draggable="false"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ item.type === 'video' ? 'Videó' : 'Kép' }}</span>

                        <span
                            class="absolute left-2 top-2 flex h-5 w-5 items-center justify-center rounded border text-[11px] font-bold"
                            :class="isSelected(item.id) ? 'border-accent bg-accent text-white' : 'border-white/70 bg-black/30 text-transparent'"
                        >✓</span>

                        <span class="absolute right-2 top-2 rounded-full border px-2 py-0.5 text-[10px] font-medium" :class="statusClass[item.status]">
                            {{ statusLabel[item.status] ?? item.status }}
                        </span>
                    </div>
                    <div class="space-y-2 p-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="truncate text-muted">{{ item.photographer?.name }}</span>
                            <span class="shrink-0 font-medium text-content">{{ huf(item.price_cents) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <button
                                type="button"
                                class="text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-content"
                                :disabled="!['ready', 'hidden'].includes(item.status)"
                                :class="!['ready', 'hidden'].includes(item.status) && 'opacity-40'"
                                @click="toggleVisibility(item)"
                            >
                                {{ item.status === 'hidden' ? 'Megjelenítés' : 'Elrejtés' }}
                            </button>
                            <button type="button" class="text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="destroyMedia(item)">
                                Törlés
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="media.data.length === 0" class="col-span-full py-10 text-center text-sm text-muted">
                    Még nincs feltöltött média ehhez az eseményhez.
                </p>
            </div>

            <!-- Drag-kijeloles vizualis kerete -->
            <div
                v-if="marquee"
                class="pointer-events-none fixed z-30 rounded border border-accent bg-accent/10"
                :style="{ left: marquee.x + 'px', top: marquee.y + 'px', width: marquee.w + 'px', height: marquee.h + 'px' }"
            />

            <div v-if="media.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                <Link
                    v-for="(link, i) in media.links"
                    :key="i"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="rounded-lg border px-3 py-1.5 text-xs"
                    :class="[
                        link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    preserve-scroll
                />
            </div>
        </div>
    </AdminLayout>
</template>
