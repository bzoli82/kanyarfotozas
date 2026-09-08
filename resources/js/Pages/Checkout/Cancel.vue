<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('checkout.cancel.title')" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">{{ t('checkout.cancel.title') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ t('checkout.cancel.body') }}</p>
                <Link href="/cart" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('checkout.cancel.back_to_cart') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
