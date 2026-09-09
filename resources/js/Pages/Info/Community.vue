<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SocialIcon from '@/Components/SocialIcon.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();
const page = usePage();
const social = computed(() => page.props.social ?? []);
</script>

<template>
    <Head :title="t('community.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('community.title') }}</h1>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('community.subtitle') }}</p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <p v-if="social.length === 0" class="text-sm text-muted">{{ t('community.empty') }}</p>

                <ul v-else class="grid gap-4 sm:grid-cols-2">
                    <li v-for="s in social" :key="s.platform">
                        <a
                            :href="s.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group flex items-center gap-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 transition-colors hover:border-accent"
                        >
                            <SocialIcon :platform="s.platform" class="h-8 w-8 shrink-0 text-content group-hover:text-accent" />
                            <span class="min-w-0">
                                <span class="block font-display text-base font-bold text-content group-hover:text-accent">{{ s.label }}</span>
                                <span class="block truncate text-xs text-muted">{{ s.display }}</span>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-t border-border bg-surface-1">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ t('community.contact_title') }}</h2>
                <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('community.contact_body') }}</p>
                <Link href="/contact" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    {{ t('nav.contact') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
