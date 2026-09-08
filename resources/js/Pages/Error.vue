<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    status: { type: Number, default: 500 },
});

const { t } = useI18n();

const copy = computed(() => {
    const map = {
        403: { title: t('errors.403_title'), body: t('errors.403_body') },
        404: { title: t('errors.404_title'), body: t('errors.404_body') },
        419: { title: t('errors.419_title'), body: t('errors.419_body') },
        429: { title: t('errors.429_title'), body: t('errors.429_body') },
        500: { title: t('errors.500_title'), body: t('errors.500_body') },
        503: { title: t('errors.503_title'), body: t('errors.503_body') },
    };

    return map[props.status] ?? map[500];
});
</script>

<template>
    <Head :title="`${status} — ${copy.title}`" />

    <PublicLayout>
        <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6 lg:px-8">
            <div class="font-display text-7xl font-bold text-accent sm:text-8xl">{{ status }}</div>
            <h1 class="mt-4 font-display text-2xl font-bold uppercase tracking-tight text-content">{{ copy.title }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ copy.body }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link href="/" class="rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('errors.home') }}
                </Link>
                <Link href="/events" class="rounded-lg border border-border px-6 py-3 text-xs font-semibold uppercase tracking-wide text-content hover:bg-surface-1">
                    {{ t('errors.browse') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
