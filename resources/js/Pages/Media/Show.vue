<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import MediaCard from '@/Components/MediaCard.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import { useCartStore } from '@/Stores/cart';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';
import { useFormGuard } from '@/Composables/useFormGuard';
import { useHcaptcha } from '@/Composables/useHcaptcha';

const props = defineProps({
    media: Object,
    event: Object,
    photographer: Object,
    nearby: Array,
    contactGuard: { type: Object, default: null },
    contactHcaptcha: { type: Object, default: null },
});

const { question: guardQuestion, fields: guardFields } = useFormGuard(() => props.contactGuard);
const { token: hcToken, el: hcEl, reset: hcReset, isEnabled: hcIsEnabled } = useHcaptcha(() => props.contactHcaptcha);

const cart = useCartStore();
const { t, locale } = useI18n();
const { mediaUrl } = useMediaUrl();

const shotAtLabel = computed(() => {
    if (!props.media.shot_at) return null;
    const d = new Date(props.media.shot_at);
    return d.toLocaleString(locale.value === 'en' ? 'en-GB' : 'hu-HU', { dateStyle: 'medium', timeStyle: 'short' });
});

function addToCart() {
    cart.add({
        id: props.media.id,
        type: props.media.type,
        price_cents: props.media.price_cents,
        thumbnail_s3_key: props.media.thumbnail_s3_key,
        event_name: props.event.name,
    });
}

const showAskForm = ref(false);
const askForm = useForm({ name: '', email: '', message: '', guard_answer: '', website: '', nickname: '' });

async function submitAsk() {
    if (hcIsEnabled() && !hcToken.value) {
        askForm.setError('hcaptcha', t('contact.hcaptcha_required'));
        return;
    }

    const g = await guardFields();
    askForm.transform((data) => ({ ...data, ...g, 'h-captcha-response': hcToken.value })).post(`/media/${props.media.id}/question`, {
        preserveScroll: true,
        onSuccess: () => { askForm.reset(); showAskForm.value = false; hcReset(); },
        onError: (errors) => {
            askForm.reset('guard_answer');
            hcReset();
            if (errors.guard) {
                router.reload({ only: ['contactGuard'] });
            }
        },
    });
}
</script>

<template>
    <Head :title="t('media.page_title', { name: event.name })" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <Link :href="`/events/${event.slug}`" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">
                    ← {{ event.name }}
                </Link>

                <div class="mt-4 grid gap-8 lg:grid-cols-[1.6fr_1fr]">
                    <div class="overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1">
                        <VideoPlayer
                            v-if="media.type === 'video' && media.watermarked_s3_key"
                            :src="mediaUrl(media.watermarked_s3_key)"
                            :hls="media.hls_playlist_s3_key ? mediaUrl(media.hls_playlist_s3_key) : null"
                            class="aspect-video w-full"
                        />
                        <img
                            v-else-if="media.watermarked_s3_key"
                            :src="mediaUrl(media.watermarked_s3_key)"
                            class="w-full object-contain"
                            alt=""
                        />
                    </div>

                    <div>
                        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ event.name }}</h1>
                        <p class="mt-1 text-sm text-muted">
                            <span v-if="event.country">{{ event.country.flag_emoji }} {{ event.country.name }} · </span>
                            {{ event.location }}
                        </p>

                        <dl class="mt-6 space-y-2 text-sm">
                            <div v-if="shotAtLabel" class="flex justify-between border-b border-border pb-2">
                                <dt class="text-muted">{{ t('media.shot_at') }}</dt>
                                <dd class="text-content">{{ shotAtLabel }}</dd>
                            </div>
                            <div v-if="media.duration_seconds" class="flex justify-between border-b border-border pb-2">
                                <dt class="text-muted">{{ t('media.duration') }}</dt>
                                <dd class="text-content">{{ Math.floor(media.duration_seconds / 60) }}:{{ String(media.duration_seconds % 60).padStart(2, '0') }}</dd>
                            </div>
                            <div v-if="media.width && media.height" class="flex justify-between border-b border-border pb-2">
                                <dt class="text-muted">{{ t('media.resolution') }}</dt>
                                <dd class="text-content">{{ media.width }}×{{ media.height }}</dd>
                            </div>
                            <div v-if="photographer" class="flex justify-between border-b border-border pb-2">
                                <dt class="text-muted">{{ t('media.photographer') }}</dt>
                                <dd class="text-content">{{ photographer.name }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-bold text-content">{{ media.price_cents }} Ft</span>
                                <span class="text-[11px] text-muted">{{ media.type === 'video' ? t('media.formats_video') : t('media.formats_photo') }}</span>
                            </div>
                            <button
                                type="button"
                                class="mt-3 w-full rounded-lg py-2.5 text-xs font-semibold uppercase tracking-wide transition-colors"
                                :class="cart.hasItem(media.id) ? 'border border-accent text-accent' : 'bg-accent text-white hover:bg-accent-hover'"
                                @click="addToCart"
                            >
                                {{ cart.hasItem(media.id) ? t('common.in_cart_long') : t('common.add_to_cart') }}
                            </button>
                        </div>

                        <div v-if="contactGuard" class="mt-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                            <p v-if="askForm.recentlySuccessful" class="text-sm text-accent">{{ t('media.ask_photographer_sent') }}</p>
                            <template v-else>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between text-left text-sm font-semibold text-content"
                                    @click="showAskForm = !showAskForm"
                                >
                                    {{ t('media.ask_photographer') }}
                                    <span class="text-muted">{{ showAskForm ? '−' : '+' }}</span>
                                </button>
                                <form v-if="showAskForm" class="relative mt-3 space-y-2" @submit.prevent="submitAsk">
                                    <p class="text-xs text-muted">{{ t('media.ask_photographer_intro') }}</p>
                                    <div class="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                                        <input v-model="askForm.website" type="text" tabindex="-1" autocomplete="off" />
                                        <input v-model="askForm.nickname" type="text" tabindex="-1" autocomplete="off" />
                                    </div>
                                    <input v-model="askForm.name" type="text" required :placeholder="t('contact.name')" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                    <input v-model="askForm.email" type="email" required :placeholder="t('contact.email')" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                    <textarea v-model="askForm.message" rows="3" required :placeholder="t('contact.message')" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"></textarea>
                                    <div v-if="hcIsEnabled()" ref="hcEl"></div>
                                    <label v-else class="flex items-center gap-2 text-xs text-muted">
                                        {{ t('contact.captcha_label', { question: guardQuestion }) }}
                                        <input v-model="askForm.guard_answer" type="number" inputmode="numeric" required class="w-16 rounded-lg border border-border bg-surface-2 px-2 py-1.5 text-sm text-content focus:border-accent focus:outline-none" />
                                    </label>
                                    <p v-if="askForm.errors.guard_answer" class="text-xs text-accent">{{ askForm.errors.guard_answer }}</p>
                                    <p v-if="askForm.errors.guard" class="text-xs text-accent">{{ askForm.errors.guard }}</p>
                                    <p v-if="askForm.errors.hcaptcha" class="text-xs text-accent">{{ askForm.errors.hcaptcha }}</p>
                                    <p v-if="askForm.errors.email" class="text-xs text-accent">{{ askForm.errors.email }}</p>
                                    <button type="submit" :disabled="askForm.processing" class="w-full rounded-lg bg-accent py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                                        {{ t('contact.submit') }}
                                    </button>
                                </form>
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="nearby.length > 0" class="mt-12">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ t('media.nearby_title') }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MediaCard
                            v-for="item in nearby"
                            :key="item.id"
                            :media="item"
                            :event-name="event.name"
                            @select="(m) => router.visit(`/media/${m.id}`)"
                        />
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
