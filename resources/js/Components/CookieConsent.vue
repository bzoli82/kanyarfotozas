<script setup>
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useConsentStore } from '@/Stores/consent';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();
const consent = useConsentStore();
const mounted = ref(false);

// Csak kliens-oldalon jelenjen meg (SSR / hidratálás előtt ne villanjon be).
onMounted(() => {
    mounted.value = true;
});
</script>

<template>
    <Transition name="cc">
        <div
            v-if="mounted && !consent.decided"
            class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-surface-1/95 backdrop-blur"
            role="dialog"
            aria-live="polite"
            :aria-label="t('cookie.title')"
        >
            <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-xs text-muted sm:max-w-2xl">
                    {{ t('cookie.body') }}
                    <Link href="/privacy#cookies" class="whitespace-nowrap font-semibold text-accent hover:underline">
                        {{ t('cookie.more') }}
                    </Link>
                </p>
                <div class="flex shrink-0 gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-content hover:border-accent"
                        @click="consent.accept('essential')"
                    >
                        {{ t('cookie.essential') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-accent px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                        @click="consent.accept('all')"
                    >
                        {{ t('cookie.accept') }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.cc-enter-active,
.cc-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
}
.cc-enter-from,
.cc-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
