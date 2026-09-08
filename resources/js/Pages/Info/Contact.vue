<script setup>
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SocialIcon from '@/Components/SocialIcon.vue';
import { useI18n } from '@/Composables/useI18n';
import { useFormGuard } from '@/Composables/useFormGuard';
import { useHcaptcha } from '@/Composables/useHcaptcha';

const props = defineProps({
    guard: { type: Object, required: true },
    hcaptcha: { type: Object, default: () => ({ enabled: false, site_key: '' }) },
});

const { t } = useI18n();
const page = usePage();
const success = computed(() => page.props.flash?.success);
const social = computed(() => page.props.social ?? []);

const { question, fields: guardFields } = useFormGuard(() => props.guard);
const { token: hcToken, el: hcEl, reset: hcReset, isEnabled: hcIsEnabled } = useHcaptcha(() => props.hcaptcha);
const hcNeeded = computed(() => hcIsEnabled());

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
    guard_answer: '',
    website: '', // honeypot — ember nem tölti ki
    nickname: '', // honeypot
});

const hcError = computed(() => form.errors.hcaptcha);

async function submit() {
    if (hcNeeded.value && !hcToken.value) {
        form.setError('hcaptcha', t('contact.hcaptcha_required'));
        return;
    }

    const g = await guardFields();
    form.transform((data) => ({ ...data, ...g, 'h-captcha-response': hcToken.value })).post('/contact', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); hcReset(); },
        onError: (errors) => {
            form.reset('guard_answer');
            hcReset();
            // Lejárt / elhasznált challenge → friss token kérése.
            if (errors.guard) {
                router.reload({ only: ['guard'] });
            }
        },
    });
}
</script>

<template>
    <Head :title="t('contact.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('contact.title') }}</h1>
                <p class="mt-4 text-sm text-muted">{{ t('contact.subtitle') }}</p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <div v-if="success" class="rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-6 text-sm text-content">
                    {{ success }}
                </div>

                <form v-else class="relative space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('contact.name') }}</span>
                            <input v-model="form.name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-accent">{{ form.errors.name }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('contact.email') }}</span>
                            <input v-model="form.email" type="email" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-accent">{{ form.errors.email }}</p>
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('contact.subject') }}</span>
                        <input v-model="form.subject" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="form.errors.subject" class="mt-1 text-xs text-accent">{{ form.errors.subject }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('contact.message') }}</span>
                        <textarea v-model="form.message" rows="6" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-xs text-accent">{{ form.errors.message }}</p>
                    </label>

                    <div v-if="hcNeeded">
                        <div ref="hcEl"></div>
                        <p v-if="hcError" class="mt-1 text-xs text-accent">{{ hcError }}</p>
                    </div>
                    <template v-else>
                        <div class="flex items-center gap-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('contact.captcha_label', { question }) }}</span>
                            <input
                                v-model="form.guard_answer"
                                type="number"
                                inputmode="numeric"
                                class="w-20 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"
                            />
                        </div>
                        <p v-if="form.errors.guard_answer" class="-mt-3 text-xs text-accent">{{ form.errors.guard_answer }}</p>
                    </template>
                    <p v-if="form.errors.guard" class="-mt-3 text-xs text-accent">{{ form.errors.guard }}</p>

                    <!-- honeypot: vizuálisan a képernyőn kívül, csak bot tölti ki -->
                    <div class="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                        <input v-model="form.nickname" type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-accent px-8 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    >
                        {{ t('contact.submit') }}
                    </button>
                </form>

                <div v-if="social.length" class="mt-12 border-t border-border pt-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ t('contact.follow_us') }}</h2>
                    <ul class="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                        <li v-for="s in social" :key="s.platform" class="min-w-0">
                            <a
                                :href="s.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group flex items-center gap-3 text-sm hover:text-accent"
                            >
                                <SocialIcon :platform="s.platform" class="h-6 w-6 shrink-0 text-content group-hover:text-accent" />
                                <span class="truncate font-medium text-content group-hover:text-accent">{{ s.display }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
