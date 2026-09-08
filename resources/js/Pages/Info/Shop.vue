<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    basePrice: { type: Number, default: 1490 },
    bulkTiers: { type: Array, default: () => [] },
});

const { t } = useI18n();

const includes = computed(() => [1, 2, 3, 4, 5].map((n) => t(`shop.includes_${n}`)));
</script>

<template>
    <Head :title="t('shop.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('shop.title') }}</h1>
                <p class="mt-4 text-sm text-muted">{{ t('shop.subtitle') }}</p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted">{{ t('shop.photo') }}</h2>
                        <p class="mt-2 text-2xl font-bold text-content">{{ t('shop.from', { price: basePrice }) }}</p>
                        <p class="mt-1 text-xs text-muted">{{ t('shop.per_item') }}</p>
                    </div>
                    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted">{{ t('shop.video') }}</h2>
                        <p class="mt-2 text-2xl font-bold text-content">{{ t('shop.from', { price: Math.round(basePrice * 1.3) }) }}</p>
                        <p class="mt-1 text-xs text-muted">{{ t('shop.per_clip') }}</p>
                    </div>
                </div>

                <div v-if="bulkTiers.length" class="mt-4 rounded-[var(--radius-base)] border border-accent/40 bg-accent/5 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ t('shop.bulk_title') }}</h2>
                    <ul class="mt-3 space-y-1 text-sm text-muted">
                        <li v-for="tier in bulkTiers" :key="tier.min">
                            {{ t('shop.bulk_tier', { count: tier.min, percent: tier.percent }) }}
                        </li>
                    </ul>
                    <p class="mt-2 text-xs text-muted">{{ t('shop.bulk_note') }}</p>
                </div>

                <div class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ t('shop.includes_title') }}</h2>
                    <ul class="mt-4 space-y-2 text-sm text-muted">
                        <li v-for="item in includes" :key="item" class="flex gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 shrink-0 text-accent"><path d="M20 6 9 17l-5-5" /></svg>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ t('shop.formats_title') }}</h2>
                    <div class="mt-4 grid gap-4 text-sm text-muted sm:grid-cols-2">
                        <div>
                            <p class="font-semibold text-content">JPEG</p>
                            <p class="mt-1">{{ t('shop.jpeg_desc') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-content">WebP</p>
                            <p class="mt-1">{{ t('shop.webp_desc') }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-muted">{{ t('shop.formats_note') }}</p>
                </div>

                <Link href="/events" class="mt-8 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('common.browse_galleries') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
