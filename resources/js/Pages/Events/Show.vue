<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import MediaCard from '@/Components/MediaCard.vue';
import MediaLightbox from '@/Components/MediaLightbox.vue';
import MediaHistogram from '@/Components/MediaHistogram.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const filterOptions = computed(() => [
    { v: 'all', l: t('gallery.filter_all') },
    { v: 'photo', l: t('gallery.filter_photos') },
    { v: 'video', l: t('gallery.filter_videos') },
]);

const props = defineProps({
    event: Object,
    media: Object,
    filters: Object,
});

const items = ref([...props.media.data]);
// Az elso oldalt Inertia rendereli (SEO), a tovabbiakat a JSON API-rol toltjuk —
// ezert itt sajat lapszamlalot vezetunk, fuggetlenul az Inertia paginator URL-jeitol.
const nextPage = ref(props.media.current_page < props.media.last_page ? props.media.current_page + 1 : null);
const loadingMore = ref(false);
const sentinel = ref(null);
let observer = null;

const lightboxIndex = ref(null);

// Szurovaltas (`preserveState: true` miatt a komponens NEM epul ujra) — a friss,
// szurt media-listat a props-bol vissza kell szinkronizalni az `items`/`nextPage`-be.
watch(
    () => props.media,
    (media) => {
        items.value = [...media.data];
        nextPage.value = media.current_page < media.last_page ? media.current_page + 1 : null;
        lightboxIndex.value = null;
    },
);

function openLightbox(media) {
    lightboxIndex.value = items.value.findIndex((i) => i.id === media.id);
}

function applyFilter(type) {
    router.get(`/events/${props.event.slug}`, { ...props.filters, type }, { preserveState: true, preserveScroll: true });
}

// Fejlett idopont-navigacio (EPIC-17): HH:MM kereses +-2 perc ablakban + hisztogram.
const timeQuery = ref('');

function searchTime() {
    if (!/^\d{1,2}:\d{2}$/.test(timeQuery.value)) return;
    const [h, m] = timeQuery.value.split(':').map(Number);
    const base = h * 60 + m;
    const from = Math.max(0, base - 2);
    const to = Math.min(1439, base + 2);
    const fmt = (mins) => `${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`;
    router.get(`/events/${props.event.slug}`, { ...props.filters, shot_from: fmt(from), shot_to: fmt(to) }, { preserveState: true, preserveScroll: true });
}

function pickHour(hour) {
    const h = String(hour).padStart(2, '0');
    router.get(`/events/${props.event.slug}`, { ...props.filters, shot_from: `${h}:00`, shot_to: `${h}:59` }, { preserveState: true, preserveScroll: true });
}

function clearTimeFilter() {
    timeQuery.value = '';
    const { shot_from, shot_to, ...rest } = props.filters;
    router.get(`/events/${props.event.slug}`, rest, { preserveState: true, preserveScroll: true });
}

const hasTimeFilter = computed(() => Boolean(props.filters.shot_from || props.filters.shot_to));

// Esemeny-ertesito feliratkozas (announced allapot)
const subscribeEmail = ref('');
const subscribeState = ref('idle'); // idle | sending | done | error

async function subscribe() {
    if (!subscribeEmail.value) return;
    subscribeState.value = 'sending';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/api/subscriptions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf ?? '' },
            body: JSON.stringify({
                email: subscribeEmail.value,
                location: props.event.location,
                country_code: props.event.country?.code ?? null,
            }),
        });
        subscribeState.value = res.ok ? 'done' : 'error';
    } catch (e) {
        subscribeState.value = 'error';
    }
}

async function loadMore() {
    if (!nextPage.value || loadingMore.value) return;
    loadingMore.value = true;

    try {
        const params = new URLSearchParams({ ...props.filters, page: nextPage.value });
        const res = await fetch(`/api/events/${props.event.id}/media?${params.toString()}`);
        const json = await res.json();

        items.value.push(...json.data);
        nextPage.value = json.next_page_url ? nextPage.value + 1 : null;
    } finally {
        loadingMore.value = false;
    }
}

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) loadMore();
    });
    if (sentinel.value) observer.observe(sentinel.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head :title="event.name" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content sm:text-3xl">{{ event.name }}</h1>
                <p class="mt-1 text-sm text-muted">
                    <span v-if="event.country">{{ event.country.flag_emoji }} {{ event.country.name }} · </span>
                    {{ event.location }} · {{ event.event_date }}
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button
                        v-for="opt in filterOptions"
                        :key="opt.v"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="filters.type === opt.v ? 'border-accent bg-accent text-white' : 'border-border text-muted hover:text-content'"
                        @click="applyFilter(opt.v)"
                    >
                        {{ opt.l }}
                    </button>

                    <span v-if="event.status === 'live'" class="mx-1 h-4 w-px bg-border" />

                    <form v-if="event.status === 'live'" class="flex items-center gap-1.5" @submit.prevent="searchTime">
                        <input
                            v-model="timeQuery"
                            type="text"
                            placeholder="09:45"
                            maxlength="5"
                            class="w-20 rounded-full border border-border bg-surface-2 px-3 py-1.5 text-center text-xs text-content focus:border-accent focus:outline-none"
                        />
                        <button type="submit" class="rounded-full border border-border px-3 py-1.5 text-xs font-medium text-muted hover:text-content">
                            Időpont
                        </button>
                    </form>
                    <button
                        v-if="hasTimeFilter"
                        type="button"
                        class="text-[11px] font-semibold uppercase tracking-wide text-muted underline hover:text-content"
                        @click="clearTimeFilter"
                    >
                        Időszűrő törlése
                    </button>
                </div>

                <MediaHistogram v-if="event.status === 'live'" :event-id="event.id" @pick-hour="pickHour" />
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div v-if="event.status === 'announced'" class="mx-auto max-w-md rounded-[var(--radius-base)] border border-dashed border-border bg-surface-1 p-8 text-center">
                    <p class="text-sm text-muted">{{ t('gallery.announced') }}</p>

                    <form v-if="subscribeState !== 'done'" class="mt-6 space-y-3" @submit.prevent="subscribe">
                        <label class="block text-left">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Értesítést kérek, ha elérhető</span>
                            <input
                                v-model="subscribeEmail"
                                type="email"
                                placeholder="te@pelda.hu"
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                            />
                        </label>
                        <button
                            type="submit"
                            :disabled="subscribeState === 'sending'"
                            class="w-full rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                        >
                            {{ subscribeState === 'sending' ? 'Feliratkozás…' : 'Értesítést kérek' }}
                        </button>
                        <p v-if="subscribeState === 'error'" class="text-xs text-accent">Nem sikerült a feliratkozás. Próbáld újra.</p>
                    </form>
                    <p v-else class="mt-6 rounded-lg border border-accent/40 bg-accent/10 p-3 text-sm text-content">
                        Feliratkoztál — e-mailt küldünk, amint az „{{ event.name }}” felvételei elérhetők.
                    </p>
                </div>

                <div v-else-if="items.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border bg-surface-1 p-10 text-center text-sm text-muted">
                    {{ t('gallery.empty') }}
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MediaCard v-for="item in items" :key="item.id" :media="item" :event-name="event.name" @select="openLightbox" />
                </div>

                <div ref="sentinel" class="h-4"></div>
                <div v-if="loadingMore" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-hidden="true">
                    <div
                        v-for="n in 4"
                        :key="n"
                        class="animate-pulse overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1"
                    >
                        <div class="aspect-[4/3] bg-surface-2"></div>
                        <div class="flex items-center justify-between gap-2 p-3">
                            <span class="h-4 w-16 rounded bg-surface-2"></span>
                            <span class="h-6 w-20 rounded bg-surface-2"></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Transition name="lb">
            <MediaLightbox
                v-if="lightboxIndex !== null"
                :items="items"
                :index="lightboxIndex"
                :event="event"
                @update:index="lightboxIndex = $event"
                @close="lightboxIndex = null"
                @load-more="loadMore"
            />
        </Transition>
    </PublicLayout>
</template>
