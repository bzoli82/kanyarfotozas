<script setup>
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useCartStore } from '@/Stores/cart';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    paid: Boolean,
    orderNumber: { type: String, default: null },
    downloadUrl: { type: String, default: null },
});

const cart = useCartStore();

onMounted(() => {
    if (props.paid) {
        cart.clear();
    }
});
</script>

<template>
    <Head :title="t('checkout.success.title')" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <template v-if="paid">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-accent/10 text-accent">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" /></svg>
                    </div>
                    <h1 class="font-display mt-5 text-2xl font-bold uppercase tracking-tight text-content">{{ t('checkout.success.title') }}</h1>
                    <p v-if="orderNumber" class="mt-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('download.order_number') }}: {{ orderNumber }}</p>
                    <p class="mt-2 text-sm text-muted">{{ t('checkout.success.body') }}</p>
                    <Link :href="downloadUrl" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                        {{ t('checkout.success.go_to_download') }}
                    </Link>
                </template>
                <template v-else>
                    <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">{{ t('checkout.processing.title') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ t('checkout.processing.body') }}</p>
                </template>

                <div class="mt-8">
                    <Link href="/events" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">
                        {{ t('checkout.back_to_galleries') }}
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
