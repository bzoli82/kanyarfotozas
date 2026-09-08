<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const steps = computed(() => [1, 2, 3, 4].map((n) => ({
    title: t(`about.step${n}_title`),
    desc: t(`about.step${n}_desc`),
})));
</script>

<template>
    <Head :title="t('about.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('about.title') }}</h1>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('about.intro') }}</p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ t('about.how_title') }}</h2>
                <div class="mt-6 space-y-6">
                    <div v-for="(step, i) in steps" :key="step.title" class="flex gap-4">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-accent text-sm font-bold text-accent">{{ i + 1 }}</span>
                        <div>
                            <h3 class="text-sm font-semibold text-content">{{ step.title }}</h3>
                            <p class="mt-1 text-sm text-muted">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="photographers" class="border-t border-border bg-surface-1">
            <div class="mx-auto max-w-3xl px-4 py-12 text-center sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ t('about.photographers_title') }}</h2>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('about.photographers_body') }}</p>
                <Link href="/photographers" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('photographers.title') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
