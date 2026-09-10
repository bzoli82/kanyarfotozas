<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import MediaCard from '@/Components/MediaCard.vue';
import MediaLightbox from '@/Components/MediaLightbox.vue';
import MediaHistogram from '@/Components/MediaHistogram.vue';
import { useI18n } from '@/Composables/useI18n';

const { t, locale } = useI18n();
const nf = new Intl.NumberFormat(locale.value === 'en' ? 'en-GB' : 'hu-HU');

const filterOptions = computed(() => [
    { v: 'all', l: t('gallery.filter_all') },
    { v: 'photo', l: t('gallery.filter_photos') },
    { v: 'video', l: t('gallery.filter_videos') },
]);

const props = defineProps({
    event: Object,
    media: Object,
    filters: Object,
    perPage: { type: Number, default: 50 },
    perPageOptions: { type: Array, default: () => [10, 25, 50, 100, 250, 500] },
});

const items = computed(() => props.media.data);
const perPage = ref(props.perPage);
watch(() => props.perPage, (v) => { perPage.value = v; });

const lightboxIndex = ref(null);
// A lapváltás után újra kell nyitni a lightboxot (első vagy utolsó képnél).
const pendingLightbox = ref(null);
// A „belenő a nagykép" View Transition alatt kikapcsoljuk a <Transition name="lb">
// CSS-fade-jét, hogy a nyitó snapshot ne opacity:0-nál készüljön.
const lbCss = ref(true);

const hasPrevPage = computed(() => Boolean(props.media.prev_page_url));
const hasNextPage = computed(() => Boolean(props.media.next_page_url));

// Szurovaltas / lapozas (`preserveState: true` — a komponens NEM epul ujra).
watch(
    () => props.media,
    () => {
        if (pendingLightbox.value === 'first') {
            lightboxIndex.value = 0;
        } else if (pendingLightbox.value === 'last') {
            lightboxIndex.value = Math.max(0, items.value.length - 1);
        } else {
            lightboxIndex.value = null;
        }
        pendingLightbox.value = null;
    },
);

/**
 * Galéria → nagykép közös-elem áttűnés (View Transitions API): a rákattintott
 * bélyegkép „belenő" a teljes képernyős nézetbe. Csak nyitáskor, csak képnél;
 * záráskor a <Transition name="lb"> egyszerű fade viszi.
 */
function canMorph(el, media) {
    return Boolean(
        el
        && media
        && media.type !== 'video'
        && typeof document !== 'undefined'
        && document.startViewTransition
        && !document.hidden
        && document.documentElement.dataset.animLightbox !== 'off'
        && !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches,
    );
}

function openLightbox(payload) {
    const media = payload?.media ?? payload;
    const el = payload?.el ?? null;
    const idx = items.value.findIndex((i) => i.id === media.id);
    if (idx < 0) return;

    if (!canMorph(el, media)) {
        lightboxIndex.value = idx;

        return;
    }

    el.style.viewTransitionName = 'lightbox-media';
    lbCss.value = false;
    const vt = document.startViewTransition(async () => {
        // A régi elemről MÉG a mountolás előtt levesszük a nevet, hogy a nagykép
        // burka legyen az egyetlen `lightbox-media` az „új" snapshot pillanatában.
        el.style.viewTransitionName = '';
        lightboxIndex.value = idx;
        await nextTick();
    });
    const restore = () => { lbCss.value = true; };
    const swallow = () => {};
    vt.updateCallbackDone?.catch(swallow);
    vt.ready?.catch(swallow);
    vt.finished?.then(restore, restore);
}

/** A jelenlegi szűrők tiszta query-paraméterként (az „all" / üres kihagyva). */
function queryParams(extra = {}) {
    const p = {};
    if (props.filters.type && props.filters.type !== 'all') p.type = props.filters.type;
    if (props.filters.shot_from) p.shot_from = props.filters.shot_from;
    if (props.filters.shot_to) p.shot_to = props.filters.shot_to;
    if (perPage.value !== 50) p.per_page = perPage.value;

    return { ...p, ...extra };
}

function scrollToTop() {
    document.getElementById('gallery-top')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function goToPage(url, reopen = null) {
    if (!url) return;
    pendingLightbox.value = reopen;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: Boolean(reopen),
        onSuccess: () => {
            if (!reopen) scrollToTop();
        },
    });
}

function changePerPage() {
    router.get(`/events/${props.event.slug}`, queryParams({ per_page: perPage.value, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
    });
}

function applyFilter(type) {
    router.get(`/events/${props.event.slug}`, queryParams({ type, page: 1 }), { preserveState: true, preserveScroll: true });
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
    router.get(`/events/${props.event.slug}`, queryParams({ shot_from: fmt(from), shot_to: fmt(to), page: 1 }), { preserveState: true, preserveScroll: true });
}

function pickHour(hour) {
    const h = String(hour).padStart(2, '0');
    router.get(`/events/${props.event.slug}`, queryParams({ shot_from: `${h}:00`, shot_to: `${h}:59`, page: 1 }), { preserveState: true, preserveScroll: true });
}

function clearTimeFilter() {
    timeQuery.value = '';
    const p = queryParams({ page: 1 });
    delete p.shot_from;
    delete p.shot_to;
    router.get(`/events/${props.event.slug}`, p, { preserveState: true, preserveScroll: true });
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

// A lightbox szélső képénél a nyíl a szomszéd oldalra lép, és ott nyitja újra.
function lightboxNextPage() {
    goToPage(props.media.next_page_url, 'first');
}

function lightboxPrevPage() {
    goToPage(props.media.prev_page_url, 'last');
}
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

                <template v-else>
                    <div id="gallery-top" class="mb-4 flex flex-wrap items-center justify-between gap-3 scroll-mt-24">
                        <p class="text-xs text-muted">
                            {{ t('gallery.showing', { from: nf.format(media.from), to: nf.format(media.to), total: nf.format(media.total) }) }}
                        </p>
                        <label class="flex items-center gap-2 text-xs text-muted">
                            {{ t('gallery.per_page') }}
                            <select
                                v-model.number="perPage"
                                class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                                @change="changePerPage"
                            >
                                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MediaCard v-for="item in items" :key="item.id" :media="item" :event-name="event.name" @select="openLightbox" />
                    </div>

                    <nav v-if="media.last_page > 1" class="mt-8 flex flex-col items-center gap-3">
                        <div class="flex flex-wrap items-center justify-center gap-1">
                            <button
                                v-for="(link, i) in media.links"
                                :key="i"
                                type="button"
                                :disabled="!link.url || link.active"
                                v-html="link.label"
                                class="rounded-[var(--radius-base)] border px-3 py-1.5 text-xs disabled:cursor-default"
                                :class="[
                                    link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                                    !link.url && 'opacity-40',
                                ]"
                                @click="goToPage(link.url)"
                            />
                        </div>
                        <p class="text-[11px] text-muted">{{ t('gallery.page_of', { current: media.current_page, last: media.last_page }) }}</p>
                    </nav>
                </template>
            </div>
        </section>

        <Transition name="lb" :css="lbCss">
            <MediaLightbox
                v-if="lightboxIndex !== null"
                :items="items"
                :index="lightboxIndex"
                :event="event"
                :has-prev-page="hasPrevPage"
                :has-next-page="hasNextPage"
                @update:index="lightboxIndex = $event"
                @close="lightboxIndex = null"
                @prev-page="lightboxPrevPage"
                @next-page="lightboxNextPage"
            />
        </Transition>
    </PublicLayout>
</template>
