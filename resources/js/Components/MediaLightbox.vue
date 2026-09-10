<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useCartStore } from '@/Stores/cart';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';
import { useFlyToCart } from '@/Composables/useFlyToCart';
import VideoPlayer from '@/Components/VideoPlayer.vue';

const { t, locale } = useI18n();
const { mediaUrl } = useMediaUrl();
const { flyToCart } = useFlyToCart();

const props = defineProps({
    items: { type: Array, required: true },
    index: { type: Number, required: true },
    event: { type: Object, required: true },
});

const emit = defineEmits(['close', 'update:index', 'load-more']);

const cart = useCartStore();
const stripEl = ref(null);
const dialogEl = ref(null);
const stageImgEl = ref(null);
let restoreScrollY = 0;
let previouslyFocused = null;

const current = computed(() => props.items[props.index] ?? null);

const shotAtLabel = computed(() => {
    if (!current.value?.shot_at) return null;
    return new Date(current.value.shot_at).toLocaleString(locale.value === 'en' ? 'en-GB' : 'hu-HU', { dateStyle: 'medium', timeStyle: 'short' });
});

function durationLabel(seconds) {
    const s = Math.max(0, Math.floor(seconds ?? 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function go(delta) {
    const next = props.index + delta;
    if (next < 0 || next >= props.items.length) return;
    emit('update:index', next);
}

function jumpTo(i) {
    emit('update:index', i);
}

function toggleCart() {
    if (!current.value) return;
    if (cart.hasItem(current.value.id)) {
        cart.remove(current.value.id);

        return;
    }
    const url = current.value.thumbnail_s3_key ? mediaUrl(current.value.thumbnail_s3_key) : null;
    flyToCart(stageImgEl.value, url);
    cart.add({
        id: current.value.id,
        type: current.value.type,
        price_cents: current.value.price_cents,
        thumbnail_s3_key: current.value.thumbnail_s3_key,
        event_name: props.event.name,
    });
}

function onKey(e) {
    if (e.key === 'ArrowLeft') go(-1);
    else if (e.key === 'ArrowRight') go(1);
    else if (e.key === 'Escape') emit('close');
}

function centerActiveThumb() {
    nextTick(() => {
        const el = stripEl.value?.querySelector('[data-active="true"]');
        el?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    });
}

// A galeria vegehez kozeledve toltsuk be a kovetkezo oldalt (a szulo items-e bovul)
watch(() => props.index, (i) => {
    centerActiveThumb();
    if (i >= props.items.length - 3) emit('load-more');
});

onMounted(() => {
    // Body scroll-lock a gorgetesi pozicio megtartasaval (position: fixed technika) —
    // igy zaraskor pontosan oda ter vissza a galeria, ahol a felhasznalo tartott.
    restoreScrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${restoreScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    window.addEventListener('keydown', onKey);
    centerActiveThumb();

    // Fokusz a modálra (billentyűzet-navigáció), záráskor visszaadjuk oda, ahol volt.
    previouslyFocused = document.activeElement;
    nextTick(() => dialogEl.value?.focus());
});

onBeforeUnmount(() => {
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    window.removeEventListener('keydown', onKey);
    window.scrollTo(0, restoreScrollY);
    if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
});
</script>

<template>
    <div
        ref="dialogEl"
        role="dialog"
        aria-modal="true"
        :aria-label="event.name"
        tabindex="-1"
        class="fixed inset-0 z-50 flex flex-col bg-black focus:outline-none"
        @click.self="emit('close')"
    >
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ event.name }}</p>
                <p class="truncate text-xs text-white/50">
                    <span v-if="event.country">{{ event.country.flag_emoji }} {{ event.country.name }} · </span>{{ event.location }}
                </p>
            </div>
            <button type="button" :aria-label="t('common.close')" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-white/20 text-white/80 hover:text-white" @click="emit('close')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-4 px-4 pb-4 lg:flex-row" @click.self="emit('close')">
            <!-- Média + léptető nyilak -->
            <div class="relative flex min-h-0 flex-1 items-center justify-center" @click.self="emit('close')">
                <button
                    type="button"
                    :aria-label="t('common.previous')"
                    :disabled="index === 0"
                    class="absolute left-0 z-10 grid h-11 w-11 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80 disabled:opacity-30"
                    @click="go(-1)"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" /></svg>
                </button>

                <VideoPlayer
                    v-if="current && current.type === 'video' && current.watermarked_s3_key"
                    :key="`v-${current.id}`"
                    :src="mediaUrl(current.watermarked_s3_key)"
                    :hls="current.hls_playlist_s3_key ? mediaUrl(current.hls_playlist_s3_key) : null"
                />
                <img
                    v-else-if="current"
                    :key="`i-${current.id}`"
                    ref="stageImgEl"
                    :src="mediaUrl(current.watermarked_s3_key ?? current.thumbnail_s3_key)"
                    class="max-h-full max-w-full rounded-[var(--radius-base)] object-contain"
                    decoding="async"
                    :alt="`${event.name}${shotAtLabel ? ' — ' + shotAtLabel : ''}`"
                />

                <button
                    type="button"
                    :aria-label="t('common.next')"
                    :disabled="index >= items.length - 1"
                    class="absolute right-0 z-10 grid h-11 w-11 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80 disabled:opacity-30"
                    @click="go(1)"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6" /></svg>
                </button>
            </div>

            <!-- Adatok + kosárba — a kártya függőleges közepe a médiáéhoz igazítva -->
            <aside v-if="current" class="flex shrink-0 flex-col justify-center lg:w-[240px]">
                <div class="rounded-[var(--radius-base)] border border-white/15 bg-white/5 p-3 text-white">
                    <dl class="space-y-1.5 text-xs">
                        <div v-if="shotAtLabel" class="flex justify-between border-b border-white/10 pb-1.5">
                            <dt class="text-white/50">{{ t('media.shot_at') }}</dt>
                            <dd>{{ shotAtLabel }}</dd>
                        </div>
                        <div v-if="current.duration_seconds" class="flex justify-between border-b border-white/10 pb-1.5">
                            <dt class="text-white/50">{{ t('media.duration') }}</dt>
                            <dd>{{ durationLabel(current.duration_seconds) }}</dd>
                        </div>
                        <div v-if="current.width && current.height" class="flex justify-between border-b border-white/10 pb-1.5">
                            <dt class="text-white/50">{{ t('media.resolution') }}</dt>
                            <dd>{{ current.width }}×{{ current.height }}</dd>
                        </div>
                        <div v-if="current.photographer" class="flex justify-between border-b border-white/10 pb-1.5">
                            <dt class="text-white/50">{{ t('media.photographer') }}</dt>
                            <dd>{{ current.photographer.name }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-base font-bold">{{ current.price_cents }} Ft</span>
                        <span class="text-[10px] text-white/40">{{ current.type === 'video' ? t('media.formats_video') : t('media.formats_photo') }}</span>
                    </div>
                    <button
                        type="button"
                        class="btn-sheen group/cart mt-2.5 w-full rounded-lg py-2 text-[11px] font-semibold uppercase tracking-wide transition-colors"
                        :class="cart.hasItem(current.id) ? 'border border-accent text-accent hover:border-accent/60 hover:text-accent/60' : 'bg-accent text-white hover:bg-accent-hover'"
                        :title="cart.hasItem(current.id) ? t('common.remove_from_cart') : t('common.add_to_cart')"
                        @click="toggleCart"
                    >
                        <span v-if="!cart.hasItem(current.id)">{{ t('common.add_to_cart') }}</span>
                        <span v-else>
                            <span class="group-hover/cart:hidden">{{ t('common.in_cart_long') }}</span>
                            <span class="hidden group-hover/cart:inline">{{ t('common.remove_from_cart') }}</span>
                        </span>
                    </button>
                    <p class="mt-2.5 border-t border-white/10 pt-2 text-center text-[11px] text-white/35">{{ index + 1 }} / {{ items.length }}</p>
                </div>
            </aside>
        </div>

        <!-- Filmszalag: előző/következő képek -->
        <div ref="stripEl" class="flex shrink-0 gap-2 overflow-x-auto border-t border-white/10 bg-black/60 px-4 py-3">
            <button
                v-for="(item, i) in items"
                :key="item.id"
                type="button"
                :data-active="i === index"
                class="relative h-14 w-20 shrink-0 overflow-hidden rounded border-2 transition-colors"
                :class="i === index ? 'border-accent' : 'border-transparent opacity-60 hover:opacity-100'"
                @click="jumpTo(i)"
            >
                <img v-if="item.thumbnail_s3_key" :src="mediaUrl(item.thumbnail_s3_key)" class="h-full w-full object-cover" loading="lazy" alt="" />
                <span v-if="item.type === 'video'" class="absolute bottom-0.5 right-0.5 rounded bg-black/70 px-1 text-[9px] font-semibold text-white">▶</span>
            </button>
        </div>
    </div>
</template>
