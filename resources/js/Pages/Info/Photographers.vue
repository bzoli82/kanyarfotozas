<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SocialIcon from '@/Components/SocialIcon.vue';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const SOCIALS = [
    ['facebook', 'Facebook'],
    ['instagram', 'Instagram'],
    ['youtube', 'YouTube'],
    ['tiktok', 'TikTok'],
];

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

                            <div v-if="p.contacts && Object.keys(p.contacts).length" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                <a
                                    v-if="p.contacts.email"
                                    :href="`mailto:${p.contacts.email}`"
                                    class="inline-flex items-center gap-1.5 text-xs text-muted hover:text-accent"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                                    {{ p.contacts.email }}
                                </a>
                                <a
                                    v-if="p.contacts.website"
                                    :href="p.contacts.website"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 text-xs text-muted hover:text-accent"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></svg>
                                    {{ p.contacts.website.replace(/^https?:\/\//, '') }}
                                </a>
                                <template v-for="[key, label] in SOCIALS" :key="key">
                                    <a
                                        v-if="p.contacts[key]"
                                        :href="p.contacts[key]"
                                        target="_blank"
                                        rel="noopener"
                                        :aria-label="label"
                                        :title="label"
                                        class="text-muted transition-colors hover:text-accent"
                                    >
                                        <SocialIcon :platform="key" class="h-4 w-4" />
                                    </a>
                                </template>
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
