<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    categories: Array,
});

const { t } = useI18n();

const activeCategory = ref('all');
const openId = ref(null);

const visibleCategories = computed(() =>
    activeCategory.value === 'all'
        ? props.categories
        : props.categories.filter((c) => c.key === activeCategory.value),
);

function toggle(id) {
    openId.value = openId.value === id ? null : id;
}

// Kérdés-válasz le-/felgördülés. Ki/be a /admin/settings/theme „Animációk" →
// „GY.I.K. lenyíló animáció".
const ANSWER_MS = 500;

function animationOn() {
    if (typeof document === 'undefined') return false;
    if (document.documentElement.dataset.animFaq === 'off') return false;

    return !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Csak a `height`-et animáljuk (nincs opacity — az két, versengő transitionend-et
 * adna, és a korábbi elakadást okozta). A `transition: none` + kényszerített reflow
 * garantálja, hogy a böngésző a kezdőállapotot (0 / teljes magasság) is látja,
 * mielőtt az átmenet elindul — így nincs döccenés.
 */
function onHeightEnd(el, ms, done) {
    let called = false;
    const finish = () => {
        if (called) {
            return;
        }
        called = true;
        el.removeEventListener('transitionend', handler);
        done();
    };
    const handler = (e) => {
        if (e.target === el && e.propertyName === 'height') {
            finish();
        }
    };
    el.addEventListener('transitionend', handler);
    setTimeout(finish, ms + 60);
}

function faqEnter(el, done) {
    if (!animationOn()) {
        done();

        return;
    }
    const ms = ANSWER_MS;
    el.style.overflow = 'hidden';
    el.style.height = '0';
    el.style.transition = 'none';
    void el.offsetHeight;
    const target = el.scrollHeight;
    el.style.transition = `height ${ms}ms ease`;
    el.style.height = `${target}px`;
    onHeightEnd(el, ms, done);
}

function faqAfterEnter(el) {
    el.style.height = '';
    el.style.overflow = '';
    el.style.transition = '';
}

function faqLeave(el, done) {
    if (!animationOn()) {
        done();

        return;
    }
    const ms = ANSWER_MS;
    el.style.overflow = 'hidden';
    el.style.height = `${el.scrollHeight}px`;
    el.style.transition = 'none';
    void el.offsetHeight;
    el.style.transition = `height ${ms}ms ease`;
    el.style.height = '0';
    onHeightEnd(el, ms, done);
}

// schema.org FAQPage strukturalt adat (Google rich result-hoz)
const faqSchema = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: props.categories.flatMap((c) => c.items).map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: { '@type': 'Answer', text: item.answer },
    })),
}));
</script>

<template>
    <Head :title="t('faq.title')" />

    <PublicLayout>
        <component :is="'script'" type="application/ld+json" v-html="faqSchema" />

        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('faq.title') }}</h1>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="activeCategory === 'all' ? 'border-accent bg-accent text-white' : 'border-border text-muted hover:text-content'"
                        @click="activeCategory = 'all'"
                    >
                        {{ t('common.all') }}
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.key"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="activeCategory === c.key ? 'border-accent bg-accent text-white' : 'border-border text-muted hover:text-content'"
                        @click="activeCategory = c.key"
                    >
                        {{ c.label }}
                    </button>
                </div>

                <div class="mt-8 space-y-8">
                    <div v-for="c in visibleCategories" :key="c.key">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted">{{ c.label }}</h2>
                        <div class="mt-3 divide-y divide-border rounded-[var(--radius-base)] border border-border">
                            <div v-for="item in c.items" :key="item.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left text-sm font-medium text-content"
                                    :aria-expanded="openId === item.id"
                                    @click="toggle(item.id)"
                                >
                                    <span>{{ item.question }}</span>
                                    <svg
                                        width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        class="shrink-0 transition-transform" :class="{ 'rotate-180': openId === item.id }"
                                    >
                                        <path d="M6 9l6 6 6-6" />
                                    </svg>
                                </button>
                                <Transition :css="false" @enter="faqEnter" @after-enter="faqAfterEnter" @leave="faqLeave">
                                    <div v-if="openId === item.id" class="overflow-hidden">
                                        <p class="px-4 pb-4 text-sm leading-relaxed text-muted">{{ item.answer }}</p>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
