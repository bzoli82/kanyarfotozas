<script setup>
import { computed, ref } from 'vue';
import { useCartStore } from '@/Stores/cart';
import { useCollectionStore } from '@/Stores/collection';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { t } = useI18n();
const { mediaUrl } = useMediaUrl();

const props = defineProps({
    media: { type: Object, required: true },
    eventName: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const cart = useCartStore();
const collection = useCollectionStore();
const isHovering = ref(false);
const videoEl = ref(null);
const scrubHover = ref(null); // { x, time, frameStyle } | null

// Elő-feldolgozott videó (nincs scrub sprite) → statikus poszter + lejátszás-ikon,
// nincs hover-lejátszás. A lejátszás a lightboxban indul kattintásra.
const canHoverPreview = computed(() => !!props.media.preview_sprite_s3_key);

const totalFrames = computed(() => {
    if (!props.media.preview_sprite_interval || !props.media.duration_seconds) return 0;
    return Math.max(1, Math.ceil(props.media.duration_seconds / props.media.preview_sprite_interval));
});

const spriteColumns = computed(() => Math.min(10, totalFrames.value || 1));

function formatTime(seconds) {
    const s = Math.max(0, Math.floor(seconds ?? 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function onEnterCard() {
    isHovering.value = true;
    if (!canHoverPreview.value) return;
    if (videoEl.value) {
        videoEl.value.currentTime = 0;
        videoEl.value.play().catch(() => {});
    }
}

function onLeaveCard() {
    isHovering.value = false;
    scrubHover.value = null;
    if (videoEl.value) videoEl.value.pause();
}

function onScrubMove(event) {
    if (!props.media.preview_sprite_s3_key || !totalFrames.value) return;

    const rect = event.currentTarget.getBoundingClientRect();
    const fraction = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
    const time = fraction * (props.media.duration_seconds ?? 0);
    const frameIndex = Math.min(totalFrames.value - 1, Math.floor(fraction * totalFrames.value));
    const col = frameIndex % spriteColumns.value;
    const row = Math.floor(frameIndex / spriteColumns.value);

    scrubHover.value = {
        x: fraction * 100,
        time,
        style: {
            backgroundImage: `url(${mediaUrl(props.media.preview_sprite_s3_key)})`,
            backgroundPosition: `-${col * 160}px -${row * 90}px`,
        },
    };

    if (videoEl.value && !Number.isNaN(videoEl.value.duration)) {
        videoEl.value.currentTime = time;
    }
}

function mediaSnapshot() {
    return {
        id: props.media.id,
        type: props.media.type,
        price_cents: props.media.price_cents,
        thumbnail_s3_key: props.media.thumbnail_s3_key,
        event_name: props.eventName,
    };
}

function addToCart() {
    cart.add(mediaSnapshot());
}

function toggleCollection() {
    collection.toggle(mediaSnapshot());
}
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1 transition-[transform,border-color,box-shadow] duration-300 hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-lg hover:shadow-black/20"
        @mouseenter="onEnterCard"
        @mouseleave="onLeaveCard"
    >
        <button
            type="button"
            class="absolute right-2 top-2 z-10 grid h-8 w-8 place-items-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            :class="{ 'text-accent': collection.has(media.id) }"
            :aria-label="collection.has(media.id) ? 'Eltávolítás a kollekcióból' : 'Kollekcióhoz adás'"
            @click="toggleCollection"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" :fill="collection.has(media.id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2">
                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" />
            </svg>
        </button>

        <button
            type="button"
            class="block w-full text-left"
            :aria-label="`${media.type === 'video' ? t('common.video') : t('common.photo')}${eventName ? ' — ' + eventName : ''} · ${media.price_cents} Ft`"
            @click="emit('select', media)"
        >
            <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
                <div v-if="!media.thumbnail_s3_key" class="absolute inset-0 grid place-items-center text-border">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 8h3l2-2h6l2 2h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                </div>
                <img
                    v-if="media.thumbnail_s3_key"
                    :src="mediaUrl(media.thumbnail_s3_key)"
                    class="h-full w-full object-cover transition-transform duration-[600ms] ease-out group-hover:scale-[1.05]"
                    :class="{ invisible: isHovering && media.type === 'video' && canHoverPreview }"
                    loading="lazy"
                    decoding="async"
                    alt=""
                />

                <video
                    v-if="media.type === 'video' && media.watermarked_s3_key && canHoverPreview"
                    ref="videoEl"
                    :src="mediaUrl(media.watermarked_s3_key)"
                    class="absolute inset-0 h-full w-full object-cover"
                    :class="{ invisible: !isHovering }"
                    muted
                    loop
                    playsinline
                    preload="none"
                />

                <div v-if="media.type === 'video'" class="absolute inset-0 flex items-center justify-center" :class="{ 'opacity-0': isHovering && canHoverPreview }">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-black/50 text-white">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                    </span>
                </div>

                <span v-if="media.type === 'video' && media.duration_seconds" class="absolute bottom-2 right-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                    {{ formatTime(media.duration_seconds) }}
                </span>

                <!-- Scrub sav (csak videonal, ha van sprite) -->
                <div
                    v-if="media.type === 'video' && media.preview_sprite_s3_key"
                    class="absolute inset-x-0 bottom-0 h-2.5 cursor-pointer bg-black/30 opacity-0 transition-opacity group-hover:opacity-100"
                    @mousemove="onScrubMove"
                    @mouseleave="scrubHover = null"
                >
                    <div class="h-full bg-accent/70" :style="{ width: scrubHover ? scrubHover.x + '%' : '0%' }"></div>

                    <div
                        v-if="scrubHover"
                        class="pointer-events-none absolute bottom-4 z-10 w-[160px] -translate-x-1/2 overflow-hidden rounded border border-border shadow-lg"
                        :style="{ left: scrubHover.x + '%' }"
                    >
                        <div class="h-[90px] w-[160px] bg-no-repeat" :style="scrubHover.style"></div>
                        <div class="bg-black/80 py-0.5 text-center text-[10px] font-semibold text-white">{{ formatTime(scrubHover.time) }}</div>
                    </div>
                </div>
            </div>
        </button>

        <div class="flex items-center justify-between gap-2 p-3">
            <span class="text-sm font-semibold text-content">{{ media.price_cents }} Ft</span>
            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide transition-colors"
                :class="cart.hasItem(media.id) ? 'border-accent text-accent' : 'border-border text-content hover:border-accent'"
                @click="addToCart"
            >
                {{ cart.hasItem(media.id) ? t('common.in_cart') : t('common.add_to_cart') }}
            </button>
        </div>
    </div>
</template>
