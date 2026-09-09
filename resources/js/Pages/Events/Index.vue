<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import EventSearchPanel from '@/Components/EventSearchPanel.vue';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { t, locale } = useI18n();
const { mediaUrl } = useMediaUrl();

const props = defineProps({
    events: Object,
    filters: Object,
    perPage: { type: Number, default: 20 },
    perPageOptions: { type: Array, default: () => [10, 20, 50, 100, 200] },
});

const perPage = ref(props.perPage);
watch(() => props.perPage, (value) => { perPage.value = value; });

function changePerPage() {
    router.get('/events', { ...props.filters, per_page: perPage.value }, { preserveState: true, replace: true });
}

const statusLabel = computed(() => ({
    live: t('events.status_live'),
    announced: t('events.status_announced'),
}));

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' });
}
</script>

<template>
    <Head :title="t('nav.galleries')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content sm:text-3xl">{{ t('events.title') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ t('events.subtitle') }}</p>

                <EventSearchPanel class="mt-6" :initial="filters" />
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div v-if="events.data.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border bg-surface-1 p-10 text-center text-sm text-muted">
                    {{ t('events.empty') }}
                </div>

                <div v-if="events.data.length > 0" class="mb-4 flex items-center justify-end gap-2">
                    <label for="events-per-page" class="text-xs text-muted">{{ t('events.per_page') }}</label>
                    <select
                        id="events-per-page"
                        v-model.number="perPage"
                        class="rounded-[var(--radius-base)] border border-border bg-surface-1 px-2.5 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        @change="changePerPage"
                    >
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="event in events.data"
                        :key="event.id"
                        :href="`/events/${event.slug}`"
                        v-tilt
                        class="hover-card group overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1"
                    >
                        <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
                            <img
                                v-if="event.cover_thumbnail_s3_key"
                                v-imgfade
                                :src="mediaUrl(event.cover_thumbnail_s3_key)"
                                loading="lazy"
                                decoding="async"
                                alt=""
                                class="hover-card__media h-full w-full object-cover"
                            />
                            <div v-else class="absolute inset-0 grid place-items-center text-border">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 8h3l2-2h6l2 2h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                            </div>
                            <span
                                class="absolute left-2.5 top-2.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                :class="event.status === 'live' ? 'border-accent bg-black/60 text-accent' : 'border-white/30 bg-black/60 text-white/70'"
                            >
                                {{ statusLabel[event.status] ?? event.status }}
                            </span>
                            <span class="absolute right-2.5 top-2.5 rounded bg-black/60 px-2 py-1 text-[10px] font-semibold text-white/90">
                                {{ formatDate(event.event_date) }}
                            </span>
                        </div>
                        <div class="p-4">
                            <h3 class="text-sm font-semibold text-content transition-colors duration-300 ease-out group-hover:text-accent">{{ event.name }}</h3>
                            <p class="text-xs text-muted">
                                <span v-if="event.country">{{ event.country.flag_emoji }}</span>
                                {{ event.location }} · {{ t('home.recent.media_count', { count: event.media_count }) }}
                                <span v-if="event.distance_km !== undefined && event.distance_km !== null" class="text-accent">
                                    · {{ Number(event.distance_km).toFixed(1) }} km
                                </span>
                            </p>
                        </div>
                    </Link>
                </div>

                <div v-if="events.links?.length > 3" class="mt-8 flex flex-wrap justify-center gap-1">
                    <Link
                        v-for="(link, i) in events.links"
                        :key="i"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="rounded-[var(--radius-base)] border px-3 py-1.5 text-xs"
                        :class="[
                            link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
