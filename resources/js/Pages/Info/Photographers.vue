<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { t } = useI18n();
const { mediaUrl } = useMediaUrl();

defineProps({
    photographers: { type: Array, default: () => [] },
});

function initials(name) {
    return (name || '?')
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p.charAt(0).toUpperCase())
        .join('');
}
</script>

<template>
    <Head :title="t('photographers.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('photographers.title') }}</h1>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('photographers.subtitle') }}</p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <p v-if="photographers.length === 0" class="text-sm text-muted">{{ t('photographers.empty') }}</p>

                <ul v-else class="space-y-8">
                    <li v-for="(p, i) in photographers" :key="i" class="flex items-start gap-5">
                        <img
                            v-if="p.avatar"
                            :src="mediaUrl(p.avatar)"
                            :alt="p.name"
                            loading="lazy"
                            decoding="async"
                            class="h-20 w-20 shrink-0 rounded-full border border-border object-cover sm:h-24 sm:w-24"
                        />
                        <span
                            v-else
                            class="grid h-20 w-20 shrink-0 place-items-center rounded-full border border-border bg-surface-1 font-display text-xl font-bold text-muted sm:h-24 sm:w-24"
                            aria-hidden="true"
                        >{{ initials(p.name) }}</span>

                        <div class="min-w-0 flex-1">
                            <h2 class="font-display text-lg font-bold text-content sm:text-xl">{{ p.name }}</h2>
                            <p v-if="p.bio" class="mt-1 text-xs leading-relaxed text-muted">{{ p.bio }}</p>

                            <div v-if="Object.keys(p.socials || {}).length" class="mt-2 flex gap-3 text-xs">
                                <a v-if="p.socials.instagram" :href="p.socials.instagram" target="_blank" rel="noopener" class="text-muted hover:text-accent">Instagram</a>
                                <a v-if="p.socials.facebook" :href="p.socials.facebook" target="_blank" rel="noopener" class="text-muted hover:text-accent">Facebook</a>
                                <a v-if="p.socials.youtube" :href="p.socials.youtube" target="_blank" rel="noopener" class="text-muted hover:text-accent">YouTube</a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-t border-border bg-surface-1">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ t('about.photographers_title') }}</h2>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('about.photographers_body') }}</p>
                <Link href="/contact" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('about.photographers_cta') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
