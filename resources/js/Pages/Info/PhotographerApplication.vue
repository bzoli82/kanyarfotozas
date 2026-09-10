<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import FeedbackModal from '@/Components/FeedbackModal.vue';
import { useI18n } from '@/Composables/useI18n';
import { useFormGuard } from '@/Composables/useFormGuard';
import { useHcaptcha } from '@/Composables/useHcaptcha';

const props = defineProps({
    guard: { type: Object, required: true },
    hcaptcha: { type: Object, default: () => ({ enabled: false, site_key: '' }) },
});

const { t } = useI18n();

const showSuccess = ref(false);
const showError = ref(false);
const errorList = ref([]);
const modalOpen = computed(() => showSuccess.value || showError.value);
function closeModal() {
    showSuccess.value = false;
    showError.value = false;
}

const { question, fields: guardFields } = useFormGuard(() => props.guard);
const { token: hcToken, el: hcEl, reset: hcReset, isEnabled: hcIsEnabled } = useHcaptcha(() => props.hcaptcha);
const hcNeeded = computed(() => hcIsEnabled());

const form = useForm({
    name: '',
    email: '',
    portfolio: '',
    region: '',
    shoots: '',
    message: '',
    guard_answer: '',
    website: '',
    nickname: '',
});

const hcError = computed(() => form.errors.hcaptcha);
const perks = computed(() => [t('apply.perk_1'), t('apply.perk_2'), t('apply.perk_3'), t('apply.perk_4')]);

async function submit() {
    if (hcNeeded.value && !hcToken.value) {
        form.setError('hcaptcha', t('contact.hcaptcha_required'));

        return;
    }

    const g = await guardFields();
    form.transform((data) => ({ ...data, ...g, 'h-captcha-response': hcToken.value })).post('/csatlakozz', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            hcReset();
            showError.value = false;
            showSuccess.value = true;
        },
        onError: (errors) => {
            form.reset('guard_answer');
            hcReset();
            errorList.value = [...new Set(Object.values(errors).filter(Boolean))];
            showSuccess.value = false;
            showError.value = true;
            if (errors.guard) {
                router.reload({ only: ['guard'] });
            }
        },
    });
}
</script>

<template>
    <Head :title="t('apply.title')" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="font-display mb-5 text-sm font-bold tracking-tight text-muted">
                    <BrandLogo />
                </div>
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ t('apply.title') }}</h1>
                <p class="mt-4 text-sm text-muted">{{ t('apply.subtitle') }}</p>

                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <div v-for="perk in perks" :key="perk" class="flex items-start gap-2 text-xs text-muted">
                        <svg class="mt-0.5 shrink-0 text-accent" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg>
                        {{ perk }}
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <form class="relative space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.name') }}</span>
                            <input v-model="form.name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-accent">{{ form.errors.name }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.email') }}</span>
                            <input v-model="form.email" type="email" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-accent">{{ form.errors.email }}</p>
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.portfolio') }}</span>
                        <input v-model="form.portfolio" type="text" placeholder="https://" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="form.errors.portfolio" class="mt-1 text-xs text-accent">{{ form.errors.portfolio }}</p>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block truncate text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.region') }}</span>
                            <input v-model="form.region" type="text" :placeholder="t('apply.region_ph')" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block truncate text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.shoots') }}</span>
                            <input v-model="form.shoots" type="text" :placeholder="t('apply.shoots_ph')" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('apply.message') }}</span>
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

                    <div class="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                        <input v-model="form.nickname" type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-sheen rounded-lg bg-accent px-8 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    >
                        {{ t('apply.submit') }}
                    </button>
                </form>

                <p class="mt-12 border-t border-border pt-8 text-sm text-muted">
                    {{ t('contact.social_moved') }}
                    <Link href="/photographers" class="font-medium text-accent hover:underline">{{ t('nav.photographers') }}</Link>
                </p>
            </div>
        </section>

        <FeedbackModal
            :open="modalOpen"
            :type="showSuccess ? 'success' : 'error'"
            :title="showSuccess ? t('apply.success_title') : t('apply.error_title')"
            :message="showSuccess ? t('apply.success_body') : errorList"
            :close-label="showSuccess ? t('common.ok') : t('common.close')"
            @close="closeModal"
        />
    </PublicLayout>
</template>
