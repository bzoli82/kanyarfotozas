<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import EventSearchPanel from '@/Components/EventSearchPanel.vue';
import PaymentLogos from '@/Components/PaymentLogos.vue';
import CountUp from '@/Components/CountUp.vue';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { t } = useI18n();
const { mediaUrl } = useMediaUrl();

// Animáció-beállítások (/admin/settings/theme „Animációk" panel).
const anim = computed(() => usePage().props.animation ?? { enabled: true, hero: 'full', counters: true });
const heroMode = computed(() => (anim.value.enabled ? anim.value.hero : 'none'));
const heroFull = computed(() => heroMode.value === 'full');
const kenBurns = computed(() => heroMode.value !== 'none');
const countersOn = computed(() => anim.value.enabled && anim.value.counters);

const props = defineProps({
    // [{ type: 'image'|'video', url, poster }] — admin: /admin/settings/hero
    heroSlides: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ photos: 0, videos: 0, locations: 0, countries: 0 }) },
});

const nf = new Intl.NumberFormat('hu-HU');

const heroIndex = ref(0);
const heroVideoEls = ref([]);
let heroTimer = null;

const reduceMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function setHeroVideo(el, i) {
    if (el) {
        heroVideoEls.value[i] = el;
    }
}

function syncHeroVideos() {
    heroVideoEls.value.forEach((video, i) => {
        if (!video) {
            return;
        }
        if (i === heroIndex.value && !reduceMotion) {
            video.currentTime = 0;
            video.play().catch(() => {});
        } else {
            video.pause();
        }
    });
}

watch(heroIndex, syncHeroVideos);

onMounted(() => {
    syncHeroVideos();
    if (props.heroSlides.length > 1) {
        heroTimer = setInterval(() => {
            heroIndex.value = (heroIndex.value + 1) % props.heroSlides.length;
        }, 7000);
    }
    loadRecentEvents();
});

onBeforeUnmount(() => {
    if (heroTimer) {
        clearInterval(heroTimer);
    }
});

// Friss fotozasok — valodi esemenyek a /api/events-bol (a legfrissebb 4, boritokeppel)
const recentEvents = ref([]);

async function loadRecentEvents() {
    try {
        const res = await fetch('/api/events');
        const json = await res.json();
        recentEvents.value = (json.data ?? []).slice(0, 4).map((event) => ({
            name: event.name,
            slug: event.slug,
            location: event.location,
            mediaCount: event.media_count,
            date: event.event_date ?? '',
            cover: event.cover_thumbnail_s3_key,
            comingSoon: event.status === 'announced',
        }));
    } catch (e) {
        recentEvents.value = [];
    }
}

const features = computed(() => [
    { icon: 'camera', title: t('home.features.team_title'), desc: t('home.features.team_desc') },
    { icon: 'clock', title: t('home.features.fast_title'), desc: t('home.features.fast_desc') },
    { icon: 'gem', title: t('home.features.quality_title'), desc: t('home.features.quality_desc') },
    { icon: 'pin', title: t('home.features.anywhere_title'), desc: t('home.features.anywhere_desc') },
]);

const steps = computed(() => [
    { title: t('home.how.step1_title'), desc: t('home.how.step1_desc') },
    { title: t('home.how.step2_title'), desc: t('home.how.step2_desc') },
    { title: t('home.how.step3_title'), desc: t('home.how.step3_desc') },
]);

const stats = computed(() => [
    { label: t('home.stats.photos'), value: props.stats.photos ?? 0 },
    { label: t('home.stats.videos'), value: props.stats.videos ?? 0 },
    { label: t('home.stats.locations'), value: props.stats.locations ?? 0 },
    { label: t('home.stats.countries'), value: props.stats.countries ?? 0 },
]);
</script>

<template>
    <Head :title="t('nav.home')" />

    <PublicLayout transparent-header>
        <!-- Hero: teljes szelessegu fotó, sotet overlay, alul balra dolt cim (Stilus 4 – parallax) -->
        <section id="hero" class="relative">
            <div class="relative h-[560px] w-full overflow-hidden sm:h-[620px] lg:h-[680px]">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_30%_20%,#2a2a2a_0%,#141414_45%,#0d0d0d_100%)]"></div>

                <template v-for="(slide, i) in heroSlides" :key="i">
                    <video
                        v-if="slide.type === 'video'"
                        :ref="(el) => setHeroVideo(el, i)"
                        :src="slide.url"
                        :poster="slide.poster || undefined"
                        muted
                        loop
                        playsinline
                        preload="metadata"
                        class="absolute inset-0 h-full w-full object-cover transition-opacity duration-[1500ms] ease-in-out"
                        :class="i === heroIndex ? 'opacity-100' : 'opacity-0'"
                    />
                    <img
                        v-else
                        :src="slide.url"
                        alt=""
                        :loading="i === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="i === 0 ? 'high' : 'auto'"
                        decoding="async"
                        class="hero-slide absolute inset-0 h-full w-full object-cover transition-opacity duration-[1500ms] ease-in-out"
                        :class="[i === heroIndex ? 'opacity-100' : 'opacity-0', i === heroIndex && kenBurns ? 'hero-slide--active' : '']"
                    />
                </template>

                <div class="absolute inset-x-0 bottom-1/3 h-px bg-white/10"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-surface-0 via-surface-0/40 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-surface-0/90 via-surface-0/20 to-transparent"></div>

                <div class="relative mx-auto flex h-full max-w-7xl flex-col justify-end px-4 pb-40 sm:px-6 lg:px-8">
                    <h1 class="font-display max-w-xl text-[36px] font-bold uppercase leading-[0.95] tracking-tight text-content sm:text-[48px] lg:text-[56px]">
                        <span class="block" :class="{ 'hero-rise': heroFull }" style="--hero-delay: 40ms">{{ t('home.hero.title_1') }}</span>
                        <span class="block" :class="{ 'hero-rise': heroFull }" style="--hero-delay: 160ms">{{ t('home.hero.title_2') }}</span>
                    </h1>
                    <p class="mt-4 max-w-md text-sm text-content/70 sm:text-base" :class="{ 'hero-rise': heroFull }" style="--hero-delay: 280ms">
                        {{ t('home.hero.subtitle') }}
                    </p>
                </div>
            </div>

            <!-- Keresopanel: a hero also szelere ulve (ugyanez a panel a /events oldalon is) -->
            <div class="relative z-10 mx-auto -mt-24 max-w-4xl px-4 sm:px-6 lg:px-8">
                <EventSearchPanel />
            </div>
        </section>

        <!-- Friss fotozasok -->
        <section class="mt-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-content">
                        {{ t('home.recent.title') }}
                    </h2>
                    <Link href="/events" class="text-xs font-semibold text-muted hover:text-content">
                        {{ t('common.view_all') }} ›
                    </Link>
                </div>

                <div v-if="recentEvents.length === 0" class="mt-5 text-sm text-muted">
                    {{ t('home.recent.empty') }}
                </div>

                <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="(ev, i) in recentEvents"
                        :key="ev.slug"
                        v-reveal="i"
                        :href="`/events/${ev.slug}`"
                        class="group overflow-hidden rounded-[var(--radius-base)] border bg-surface-1 transition-colors"
                        :class="ev.comingSoon ? 'border-accent/50' : 'border-border'"
                    >
                        <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
                            <img
                                v-if="ev.cover"
                                :src="mediaUrl(ev.cover)"
                                loading="lazy"
                                decoding="async"
                                alt=""
                                class="h-full w-full object-cover"
                                :class="{ 'opacity-60': ev.comingSoon }"
                            />
                            <div v-else class="absolute inset-0 grid place-items-center text-border">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 8h3l2-2h6l2 2h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                            </div>
                            <span
                                v-if="ev.comingSoon"
                                class="absolute left-2.5 top-2.5 rounded bg-accent px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white"
                            >
                                {{ t('home.recent.coming_soon') }}
                            </span>
                            <span v-else class="absolute left-2.5 top-2.5 rounded bg-black/60 px-2 py-1 text-[10px] font-semibold text-white/90">
                                {{ ev.date }}
                            </span>
                        </div>
                        <div class="p-3">
                            <h3 class="text-sm font-semibold text-content group-hover:text-accent">{{ ev.name }}</h3>
                            <p class="text-xs text-muted">
                                {{ ev.location }}<template v-if="!ev.comingSoon"> · {{ t('home.recent.media_count', { count: ev.mediaCount }) }}</template>
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Ikonsor elvalasztokkal -->
        <section class="mt-14 border-y border-border">
            <div class="mx-auto grid max-w-7xl grid-cols-2 divide-x divide-border border-x border-border sm:grid-cols-4 sm:border-x-0">
                <div v-for="(f, i) in features" :key="f.title" v-reveal="i" class="flex items-center gap-3 px-5 py-6">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-border text-accent">
                        <svg v-if="f.icon === 'camera'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8h3l2-2h6l2 2h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                        <svg v-else-if="f.icon === 'clock'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
                        <svg v-else-if="f.icon === 'gem'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 3 6 7 6-7M2 9l10 12L22 9M2 9h20" /></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-7.5 7-12a7 7 0 1 0-14 0c0 4.5 7 12 7 12Z" /><circle cx="12" cy="9" r="2.5" /></svg>
                    </span>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-content">{{ f.title }}</h3>
                        <p class="text-xs text-muted">{{ f.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hogyan mukodik -->
        <section class="border-b border-border bg-surface-1">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content sm:text-2xl">
                    {{ t('home.how.title') }}
                </h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-3">
                    <div v-for="(step, i) in steps" :key="step.title" v-reveal="i" class="flex flex-col gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-full border border-accent text-sm font-bold text-accent">
                            {{ i + 1 }}
                        </span>
                        <h3 class="text-sm font-semibold text-content">{{ step.title }}</h3>
                        <p class="text-sm text-muted">{{ step.desc }}</p>
                    </div>
                </div>
                <Link href="#hero" class="mt-8 inline-block rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('home.cta.find_photo') }}
                </Link>
            </div>
        </section>

        <!-- Statisztikak -->
        <section class="border-b border-border">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
                <div v-for="(s, i) in stats" :key="s.label" v-reveal="i" class="text-center">
                    <div class="font-display text-3xl font-bold text-accent sm:text-4xl">
                        <CountUp :value="s.value" :format="nf.format" :active="countersOn" />
                    </div>
                    <div class="mt-1 text-xs font-medium uppercase tracking-wide text-muted">{{ s.label }}</div>
                </div>
            </div>
        </section>

        <!-- Fizetesi modok -->
        <section>
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content sm:text-2xl">
                    {{ t('home.pay.title') }}
                </h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="(card, i) in [
                            { t: t('home.pay.no_reg_title'), d: t('home.pay.no_reg_desc') },
                            { t: t('home.pay.secure_title'), d: t('home.pay.secure_desc'), logos: true },
                            { t: t('home.pay.link_title'), d: t('home.pay.link_desc') },
                        ]"
                        :key="card.t"
                        v-reveal="i"
                        class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5"
                    >
                        <h3 class="text-sm font-semibold text-content">{{ card.t }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ card.d }}</p>
                        <PaymentLogos v-if="card.logos" class="mt-3" />
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.hero-slide {
    transform: scale(1.05);
}
.hero-slide--active {
    animation: hero-kenburns 12s ease-out forwards;
}
@keyframes hero-kenburns {
    from {
        transform: scale(1.05);
    }
    to {
        transform: scale(1.12);
    }
}
@media (prefers-reduced-motion: reduce) {
    .hero-slide--active {
        animation: none;
    }
}
</style>
