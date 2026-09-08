<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

defineProps({
    bodyHtml: { type: String, default: '' },
    updatedAt: { type: String, default: null },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('footer.terms')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('footer.terms') }}</h1>
                <p v-if="updatedAt" class="mt-3 text-xs text-muted">{{ t('legal.updated', { date: updatedAt }) }}</p>
            </div>
        </section>

        <section>
            <div v-if="bodyHtml" class="legal-prose mx-auto max-w-3xl px-4 py-10 text-sm leading-relaxed text-muted sm:px-6 lg:px-8" v-html="bodyHtml" />
            <div v-else class="mx-auto max-w-3xl px-4 py-10 text-sm text-muted sm:px-6 lg:px-8">
                {{ t('legal.empty') }}
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.legal-prose :deep(h2) {
    margin-top: 1.75rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-content);
}
.legal-prose :deep(h3) {
    margin-top: 1.25rem;
    font-weight: 600;
    color: var(--color-content);
}
.legal-prose :deep(p) {
    margin-top: 0.75rem;
}
.legal-prose :deep(em) {
    color: var(--color-muted);
}
.legal-prose :deep(ul),
.legal-prose :deep(ol) {
    margin-top: 0.75rem;
    padding-left: 1.25rem;
    list-style: disc;
}
.legal-prose :deep(ol) {
    list-style: decimal;
}
.legal-prose :deep(li) {
    margin-top: 0.25rem;
}
.legal-prose :deep(strong) {
    color: var(--color-content);
}
.legal-prose :deep(a) {
    color: var(--color-accent);
    text-decoration: underline;
}
</style>
