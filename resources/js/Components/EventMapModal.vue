<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import 'leaflet/dist/leaflet.css';

// Fotós-szűrő — a superadmin kikapcsolhatja (fotós-attribúció rejtése).
const photographerSearchEnabled = computed(() => usePage().props.photographerSearch !== false);

const props = defineProps({
    open: { type: Boolean, default: false },
    // GPS sugaras keresés (PostGIS) — kikapcsolva a „Keress itt" gomb rejtve.
    allowAreaSearch: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'select', 'search', 'search-area']);

// Szűrők a modalon belül — nem kell kilépni a térképből a szűkítéshez.
const dateFrom = defineModel('dateFrom', { default: '' });
const dateUntil = defineModel('dateUntil', { default: '' });
const photographerId = defineModel('photographerId', { default: '' });
const mediaType = defineModel('type', { default: 'all' });

const mapEl = ref(null);
const loading = ref(false);
const errorMessage = ref('');
const allEvents = ref([]);
const photographers = ref([]);

const typeOptions = [
    { value: 'all', label: 'Kép és videó' },
    { value: 'photo', label: 'Csak képek' },
    { value: 'video', label: 'Csak videók' },
];

let map = null;
let markersLayer = null;
let L = null;

async function ensureLeaflet() {
    if (!L) {
        L = await import('leaflet');
    }

    return L;
}

const filteredEvents = computed(() => {
    if (!dateFrom.value && !dateUntil.value) return allEvents.value;

    return allEvents.value.filter((event) => {
        if (!event.event_date) return true;
        if (dateFrom.value && event.event_date < dateFrom.value) return false;
        if (dateUntil.value && event.event_date > dateUntil.value) return false;
        return true;
    });
});

/**
 * Helyszinenkent osszevont pontok — a jelolőn az adott helyen tartott
 * fotozasok (esemenyek) SZAMA latszik, nem a feltoltott mediak darabszama.
 * Az azonos `location` nevu esemenyek egy jelolőbe kerulnek (az elso esemeny
 * koordinatajara), a mediaszamokat csak a popupban osszegezzuk.
 */
const groupedLocations = computed(() => {
    const groups = new Map();

    filteredEvents.value.forEach((event) => {
        const key = event.location || `${event.latitude},${event.longitude}`;
        let group = groups.get(key);

        if (!group) {
            group = {
                key,
                location: event.location,
                latitude: event.latitude,
                longitude: event.longitude,
                eventCount: 0,
                mediaCount: 0,
                events: [],
            };
            groups.set(key, group);
        }

        group.eventCount += 1;
        group.mediaCount += event.media_count ?? 0;
        group.events.push(event);
    });

    // A helyszín „élő", ha legalább egy ott tartott fotózás már live (van megvásárolható
    // kép). Ha csak előhirdetett (announced) esemény van, „hamarosan".
    return [...groups.values()].map((g) => ({
        ...g,
        isUpcoming: !g.events.some((e) => e.status === 'live'),
    }));
});

const hasUpcoming = computed(() => groupedLocations.value.some((g) => g.isUpcoming));

function markerIcon(leaflet, count, isUpcoming) {
    const cls = isUpcoming
        ? 'border-dashed border-white bg-[#f59e0b]'
        : 'border-solid border-white bg-[#e63946]';

    return leaflet.divIcon({
        className: '',
        html: `
            <div class="grid h-8 w-8 place-items-center rounded-full border-2 ${cls} text-[11px] font-bold text-white shadow-lg">
                ${count}
            </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16],
    });
}

async function initMap() {
    const leaflet = await ensureLeaflet();

    await nextTick();
    if (!mapEl.value || map) return;

    // Kozep-Europa, 6-os zoom (spec 21.10: auto-fit ha van esemeny)
    map = leaflet.map(mapEl.value, { zoomControl: true }).setView([47.5, 19.5], 6);

    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap közreműködők',
        maxZoom: 18,
    }).addTo(map);

    markersLayer = leaflet.layerGroup().addTo(map);

    loadPhotographers();
    await loadEvents();
}

async function loadPhotographers() {
    if (!photographerSearchEnabled.value) return;
    try {
        const res = await fetch('/api/photographers');
        const json = await res.json();
        photographers.value = json.data ?? [];
    } catch (e) {
        photographers.value = [];
    }
}

async function loadEvents(fitView = true) {
    if (!map) return;

    loading.value = true;
    errorMessage.value = '';

    try {
        const params = new URLSearchParams();
        if (photographerId.value) params.set('photographer_id', photographerId.value);
        if (mediaType.value && mediaType.value !== 'all') params.set('type', mediaType.value);

        const res = await fetch(`/api/events?${params.toString()}`);
        if (!res.ok) throw new Error('request-failed');
        const json = await res.json();
        allEvents.value = json.data ?? [];
        await renderMarkers(fitView);
    } catch (e) {
        errorMessage.value = 'Nem sikerült betölteni az eseményeket.';
    } finally {
        loading.value = false;
    }
}

// Fotós / médiatípus váltásakor szerver-oldali újraszűrés (a dátum kliens-oldalon marad).
watch([photographerId, mediaType], () => loadEvents(false));

async function renderMarkers(fitView = true) {
    if (!map || !markersLayer) return;

    const leaflet = await ensureLeaflet();
    markersLayer.clearLayers();

    const bounds = [];

    groupedLocations.value.forEach((group) => {
        const marker = leaflet.marker([group.latitude, group.longitude], {
            icon: markerIcon(leaflet, group.eventCount, group.isUpcoming),
        });

        const eventNames = group.events
            .map((e) => `<span style="color:#666;font-size:12px">• ${e.name}${e.event_date ? ' · ' + e.event_date : ''}${e.status !== 'live' ? ' (hamarosan)' : ''}</span>`)
            .join('<br/>');

        const summary = group.isUpcoming
            ? `${group.eventCount} előhirdetett fotózás — hamarosan`
            : `${group.eventCount} fotózás · ${group.mediaCount} db média`;

        marker.bindPopup(`
            <div style="font-family:inherit;min-width:180px">
                <strong style="display:block;margin-bottom:2px">${group.location ?? ''}</strong>
                <span style="color:#666;font-size:12px">${summary}</span><br/>
                <div style="margin-top:4px">${eventNames}</div>
            </div>
        `);

        marker.on('click', () => {
            emit('select', group);
        });

        marker.addTo(markersLayer);
        bounds.push([group.latitude, group.longitude]);
    });

    if (fitView && bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });
    }
}

// Datumszures valtozasakor csak ujrarajzoljuk a jelolőket (nincs uj lekerdezes, nincs elrepules a nezet)
watch([dateFrom, dateUntil], () => renderMarkers(false));

/**
 * "Keress itt" — a lathato terkepreszlet kozeppontjabol es sugarabol
 * (kozeppont -> eszaki-keleti sarok tavolsaga) GPS sugaras keresest indit,
 * ugyanazt a backend PostGIS ST_DWithin logikat hasznalva, mint a GPS fulon.
 */
function searchCurrentArea() {
    if (!map) return;

    const center = map.getCenter();
    const bounds = map.getBounds();
    const radiusKm = center.distanceTo(bounds.getNorthEast()) / 1000;

    emit('search-area', {
        lat: Number(center.lat.toFixed(4)),
        lon: Number(center.lng.toFixed(4)),
        radius: Math.min(50, Math.max(1, Math.round(radiusKm))),
    });
}

function destroyMap() {
    if (map) {
        map.remove();
        map = null;
        markersLayer = null;
    }
}

function onKey(e) {
    if (e.key === 'Escape' && props.open) emit('close');
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            window.addEventListener('keydown', onKey);
            await initMap();
        } else {
            window.removeEventListener('keydown', onKey);
            destroyMap();
        }
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    destroyMap();
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="emit('close')">
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Fotózások térképen"
                class="flex h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1"
            >
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-content">Fotózások térképen</h2>
                        <p class="text-xs text-muted">A jelölőn a helyszínen tartott fotózások száma látszik — kattints a kiválasztáshoz</p>
                    </div>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-[var(--radius-base)] border border-border text-muted hover:text-content"
                        aria-label="Bezárás"
                        @click="emit('close')"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <!-- Szurok a terkepen belul -->
                <div class="flex flex-wrap items-end gap-3 border-b border-border bg-surface-2/50 px-4 py-3">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Dátumtól</span>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="rounded-[var(--radius-base)] border border-border bg-surface-2 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Dátumig</span>
                        <input
                            v-model="dateUntil"
                            type="date"
                            class="rounded-[var(--radius-base)] border border-border bg-surface-2 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        />
                    </label>
                    <label v-if="photographerSearchEnabled" class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                        <select
                            v-model="photographerId"
                            class="rounded-[var(--radius-base)] border border-border bg-surface-2 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        >
                            <option value="">Összes fotós</option>
                            <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Média</span>
                        <select
                            v-model="mediaType"
                            class="rounded-[var(--radius-base)] border border-border bg-surface-2 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        >
                            <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </label>
                    <button
                        v-if="dateFrom || dateUntil || photographerId || mediaType !== 'all'"
                        type="button"
                        class="mb-0.5 text-[11px] text-muted underline hover:text-content"
                        @click="dateFrom = ''; dateUntil = ''; photographerId = ''; mediaType = 'all'"
                    >
                        szűrés törlése
                    </button>
                    <span class="ml-auto mb-0.5 text-[11px] text-muted">
                        {{ groupedLocations.length }} helyszín · {{ filteredEvents.length }} fotózás
                    </span>
                </div>

                <div class="relative flex-1">
                    <div ref="mapEl" class="h-full w-full"></div>

                    <div v-if="loading" class="pointer-events-none absolute inset-0 grid place-items-center bg-surface-1/60 text-sm text-muted">
                        Térkép betöltése…
                    </div>
                    <div v-if="!loading && errorMessage" class="pointer-events-none absolute inset-0 grid place-items-center bg-surface-1/60 text-sm text-muted">
                        {{ errorMessage }}
                    </div>
                    <div v-if="!loading && !errorMessage && filteredEvents.length === 0" class="pointer-events-none absolute left-1/2 top-4 -translate-x-1/2 rounded-[var(--radius-base)] border border-border bg-surface-1/95 px-3 py-2 text-xs text-muted">
                        Nincs a szűrésnek megfelelő fotózás.
                    </div>

                    <div
                        v-if="hasUpcoming && !loading && !errorMessage"
                        class="pointer-events-none absolute bottom-3 left-3 flex flex-col gap-1 rounded-[var(--radius-base)] border border-border bg-surface-1/95 px-3 py-2 text-[11px] text-muted"
                    >
                        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full border-2 border-white bg-[#e63946]"></span> Élő fotózás (van kép)</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full border-2 border-dashed border-white bg-[#f59e0b]"></span> Hamarosan (előhirdetve)</span>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border px-4 py-3">
                    <p class="text-xs text-muted">
                        Válassz jelölőt, vagy keress a dátumszűréssel az összes fotózás között.
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="allowAreaSearch"
                            type="button"
                            class="rounded-[var(--radius-base)] border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent"
                            @click="searchCurrentArea"
                        >
                            Keress itt
                        </button>
                        <button
                            type="button"
                            class="rounded-[var(--radius-base)] bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                            @click="emit('search')"
                        >
                            Keresés
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
