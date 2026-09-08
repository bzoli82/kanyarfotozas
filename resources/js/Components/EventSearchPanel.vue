<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import EventMapModal from '@/Components/EventMapModal.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    // A jelenlegi /events szűrők (előtöltéshez), pl. a controller `filters` propja.
    initial: { type: Object, default: () => ({}) },
});

const search = reactive({
    location: props.initial.location ?? '',
    dateFrom: props.initial.date_from ?? '',
    dateUntil: props.initial.date_to ?? '',
    time: '',
    type: props.initial.type && props.initial.type !== 'all' ? props.initial.type : 'all',
    photographerId: props.initial.photographer_id ?? '',
    lat: props.initial.lat ?? null,
    lon: props.initial.lon ?? null,
    radius: Number(props.initial.radius ?? 15),
    countries: Array.isArray(props.initial.countries) ? [...props.initial.countries] : [],
});

const photographers = ref([]);
const countries = ref([]);
const countryPanelOpen = ref(false);
const locations = ref([]);
const locationsLoading = ref(false);
const locationDropdownOpen = ref(false);

async function loadPhotographers() {
    try {
        const res = await fetch('/api/photographers');
        photographers.value = (await res.json()).data ?? [];
    } catch { photographers.value = []; }
}

async function loadCountries() {
    try {
        const res = await fetch('/api/countries');
        countries.value = (await res.json()).data ?? [];
    } catch { countries.value = []; }
}

async function loadLocations() {
    locationsLoading.value = true;
    try {
        const params = new URLSearchParams();
        search.countries.forEach((code) => params.append('countries[]', code));
        const res = await fetch(`/api/events/locations?${params.toString()}`);
        locations.value = (await res.json()).data ?? [];
    } catch {
        locations.value = [];
    } finally {
        locationsLoading.value = false;
    }
}

onMounted(() => {
    loadCountries();
    loadLocations();
    loadPhotographers();

    // Ha a lap ?view=map paraméterrel jött, egyből nyíljon a térkép.
    if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('view') === 'map') {
        mapOpen.value = true;
    }
});

watch(
    () => [...search.countries],
    () => {
        loadLocations().then(() => {
            if (search.location && !locations.value.some((l) => l.location === search.location)) {
                search.location = '';
            }
        });
    },
);

const filteredLocations = computed(() => {
    const q = search.location.trim().toLowerCase();
    if (!q) return locations.value.slice(0, 8);
    return locations.value.filter((l) => l.location.toLowerCase().includes(q)).slice(0, 8);
});

function toggleCountry(code) {
    const idx = search.countries.indexOf(code);
    if (idx === -1) search.countries.push(code);
    else search.countries.splice(idx, 1);
}

function clearCountries() {
    search.countries = [];
    countryPanelOpen.value = false;
}

function pickLocation(location) {
    search.location = location.location;
    locationDropdownOpen.value = false;
}

const countryLabel = computed(() => {
    if (search.countries.length === 0) return t('home.search.all_countries');
    if (search.countries.length === 1) {
        const c = countries.value.find((c) => c.code === search.countries[0]);
        return c ? c.name : search.countries[0];
    }
    return t('home.search.countries_selected', { count: search.countries.length });
});

const searchTabs = computed(() => [
    { key: 'default', label: t('home.search.tab_default') },
    { key: 'gps', label: t('home.search.tab_gps') },
    { key: 'map', label: t('home.search.tab_map') },
]);
const activeTab = ref(search.lat && search.lon ? 'gps' : 'default');

const mapOpen = ref(false);
const gpsError = ref('');
const locating = ref(false);

function openMap() {
    mapOpen.value = true;
}

function onMapSelectEvent(event) {
    search.location = event.location ?? '';
    mapOpen.value = false;
    submitSearch();
}

function applyGpsCoordinates() {
    const lat = Number(search.lat);
    const lon = Number(search.lon);
    if (search.lat === null || search.lon === null || search.lat === '' || search.lon === '' || Number.isNaN(lat) || Number.isNaN(lon)) {
        gpsError.value = t('home.search.gps_missing');
        return false;
    }
    if (lat < -90 || lat > 90 || lon < -180 || lon > 180) {
        gpsError.value = t('home.search.gps_invalid');
        return false;
    }
    gpsError.value = '';
    return true;
}

function clearGpsCoordinates() {
    search.lat = null;
    search.lon = null;
    gpsError.value = '';
}

function useMyLocation() {
    if (!navigator.geolocation) {
        gpsError.value = t('home.search.geo_unsupported');
        return;
    }
    locating.value = true;
    gpsError.value = '';
    navigator.geolocation.getCurrentPosition(
        (position) => {
            search.lat = Number(position.coords.latitude.toFixed(4));
            search.lon = Number(position.coords.longitude.toFixed(4));
            locating.value = false;
        },
        (error) => {
            locating.value = false;
            gpsError.value = error.code === error.PERMISSION_DENIED ? t('home.search.geo_denied') : t('home.search.geo_failed');
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
}

function onMapSearchArea({ lat, lon, radius }) {
    search.lat = lat;
    search.lon = lon;
    search.radius = radius;
    activeTab.value = 'gps';
    mapOpen.value = false;
    submitSearch();
}

function submitSearch() {
    if (activeTab.value === 'gps' && (search.lat === null || search.lon === null)) {
        if (!applyGpsCoordinates()) return;
    }

    const params = {
        location: search.location || undefined,
        date_from: search.dateFrom || undefined,
        date_to: search.dateUntil || undefined,
        type: search.type !== 'all' ? search.type : undefined,
        photographer_id: search.photographerId || undefined,
        countries: search.countries.length ? search.countries : undefined,
    };

    if (activeTab.value === 'gps' && search.lat !== null && search.lon !== null) {
        params.lat = search.lat;
        params.lon = search.lon;
        params.radius = search.radius;
    }

    router.get('/events', params, { preserveState: false });
}
</script>

<template>
    <div>
        <form
            class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 shadow-lg shadow-black/10 sm:p-5"
            @submit.prevent="submitSearch"
        >
            <div class="flex items-center gap-5 border-b border-border">
                <button
                    v-for="tab in searchTabs"
                    :key="tab.key"
                    type="button"
                    class="flex items-center gap-1.5 border-b-2 pb-2.5 text-xs font-semibold uppercase tracking-wide transition-colors"
                    :class="activeTab === tab.key ? 'border-accent text-content' : 'border-transparent text-muted hover:text-content'"
                    @click="tab.key === 'map' ? openMap() : (activeTab = tab.key)"
                >
                    <svg v-if="tab.key === 'default'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    <svg v-else-if="tab.key === 'gps'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M22 12h-3M5 12H2" /></svg>
                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 4 3 6.5v13L9 17l6 3 6-2.5v-13L15 7 9 4Z" /><path d="M9 4v13M15 7v13" /></svg>
                    {{ tab.label }}
                    <span v-if="tab.key === 'gps' && search.lat && search.lon" class="ml-0.5 h-1.5 w-1.5 rounded-full bg-accent"></span>
                </button>
            </div>

            <div v-if="activeTab === 'default'" class="grid gap-3 pt-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="relative block">
                    <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M12 3c2.5 2.5 4 5.5 4 9s-1.5 6.5-4 9c-2.5-2.5-4-5.5-4-9s1.5-6.5 4-9Z" /></svg>
                        {{ t('home.search.country') }}
                    </span>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border bg-surface-2 px-3 py-2.5 text-left text-sm hover:border-accent focus:outline-none"
                        :class="search.countries.length ? 'border-accent text-accent' : 'border-border text-content'"
                        @click="countryPanelOpen = !countryPanelOpen"
                    >
                        <span class="truncate">{{ countryLabel }}</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="m6 9 6 6 6-6" /></svg>
                    </button>

                    <div
                        v-if="countryPanelOpen"
                        class="absolute left-0 top-full z-30 mt-1 w-64 rounded-[var(--radius-base)] border border-border bg-surface-2 p-3 shadow-2xl shadow-black/40"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-content">{{ t('home.search.countries_title') }}</h3>
                            <button v-if="search.countries.length" type="button" class="text-[11px] text-muted underline hover:text-content" @click="clearCountries">
                                {{ t('home.search.clear') }}
                            </button>
                        </div>
                        <div class="mt-2 max-h-56 space-y-0.5 overflow-y-auto">
                            <label v-for="country in countries" :key="country.code" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-content hover:bg-surface-1">
                                <input type="checkbox" class="accent-[var(--color-accent)]" :checked="search.countries.includes(country.code)" @change="toggleCountry(country.code)" />
                                <span>{{ country.flag_emoji }}</span>
                                <span>{{ country.name }}</span>
                            </label>
                            <p v-if="!countries.length" class="px-2 py-1.5 text-xs text-muted">{{ t('common.loading') }}</p>
                        </div>
                        <button type="button" class="mt-2 w-full rounded-lg bg-accent py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="countryPanelOpen = false">
                            {{ t('home.search.done') }}
                        </button>
                    </div>
                </div>

                <div class="relative block">
                    <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-7.5 7-12a7 7 0 1 0-14 0c0 4.5 7 12 7 12Z" /><circle cx="12" cy="9" r="2.5" /></svg>
                        {{ t('home.search.location') }}
                    </span>
                    <input
                        v-model="search.location"
                        type="text"
                        autocomplete="off"
                        :placeholder="search.countries.length ? t('home.search.location_placeholder_countries') : t('home.search.location_placeholder')"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none"
                        @focus="locationDropdownOpen = true"
                        @blur="setTimeout(() => (locationDropdownOpen = false), 150)"
                    />
                    <div
                        v-if="locationDropdownOpen && (locationsLoading || filteredLocations.length > 0 || search.location)"
                        class="absolute left-0 top-full z-20 mt-1 w-full overflow-hidden rounded-lg border border-border bg-surface-2 shadow-xl shadow-black/40"
                    >
                        <p v-if="locationsLoading" class="px-3 py-2 text-xs text-muted">{{ t('common.loading') }}</p>
                        <template v-else-if="filteredLocations.length > 0">
                            <button
                                v-for="loc in filteredLocations"
                                :key="loc.location"
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-content hover:bg-surface-1"
                                @mousedown.prevent="pickLocation(loc)"
                            >
                                <span>{{ loc.country_flag }}</span>
                                <span>{{ loc.location }}</span>
                            </button>
                        </template>
                        <p v-else class="px-3 py-2 text-xs text-muted">
                            {{ search.countries.length ? t('home.search.no_results_countries') : t('home.search.no_results') }}
                        </p>
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                        {{ t('home.search.date') }}
                    </span>
                    <input v-model="search.dateFrom" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6" /></svg>
                        {{ t('home.search.photographer') }}
                    </span>
                    <select v-model="search.photographerId" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="">{{ t('home.search.all_photographers') }}</option>
                        <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><path d="m9 8 5 4-5 4V8Z" /></svg>
                        {{ t('home.search.type') }}
                    </span>
                    <select v-model="search.type" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="all">{{ t('home.search.type_all') }}</option>
                        <option value="photo">{{ t('home.search.type_photo') }}</option>
                        <option value="video">{{ t('home.search.type_video') }}</option>
                    </select>
                </label>
            </div>

            <div v-else-if="activeTab === 'gps'" class="pt-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('home.search.latitude') }}</span>
                        <input v-model="search.lat" type="number" step="0.0001" placeholder="47.9025" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('home.search.longitude') }}</span>
                        <input v-model="search.lon" type="number" step="0.0001" placeholder="20.3772" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('home.search.date_from') }}</span>
                        <input v-model="search.dateFrom" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('home.search.date_to') }}</span>
                        <input v-model="search.dateUntil" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                </div>

                <label class="mt-3 block max-w-sm">
                    <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <span>{{ t('home.search.radius') }}</span>
                        <span class="text-content">{{ search.radius }} km</span>
                    </span>
                    <input v-model.number="search.radius" type="range" min="1" max="50" step="1" class="w-full accent-[var(--color-accent)]" />
                </label>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        :disabled="locating"
                        class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent disabled:opacity-60"
                        @click="useMyLocation"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3" /></svg>
                        {{ locating ? t('home.search.locating') : t('home.search.use_my_location') }}
                    </button>
                    <button v-if="search.lat && search.lon" type="button" class="text-[11px] text-muted underline hover:text-content" @click="clearGpsCoordinates">
                        {{ t('home.search.clear_coords') }}
                    </button>
                </div>

                <p class="mt-2 text-[11px] text-muted">{{ t('home.search.gps_hint') }}</p>
                <p v-if="gpsError" class="mt-1 text-[11px] text-accent">{{ gpsError }}</p>
            </div>

            <div class="mt-4 flex justify-end border-t border-border pt-4">
                <button type="submit" class="rounded-lg bg-accent px-8 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('home.search.submit') }}
                </button>
            </div>
        </form>

        <EventMapModal
            :open="mapOpen"
            v-model:date-from="search.dateFrom"
            v-model:date-until="search.dateUntil"
            v-model:photographer-id="search.photographerId"
            v-model:type="search.type"
            @close="mapOpen = false"
            @select="onMapSelectEvent"
            @search="mapOpen = false; submitSearch();"
            @search-area="onMapSearchArea"
        />
    </div>
</template>
