<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Adaptív videó lejátszó (EPIC-16). Ha van HLS master playlist:
 *  - natív HLS támogatás (Safari/iOS) → közvetlenül a .m3u8,
 *  - egyébként hls.js (dinamikusan importálva, külön Vite chunk),
 *  - ha egyik sem / HLS hiba → visszaesés az MP4-re.
 */
const props = defineProps({
    src: { type: String, required: true }, // MP4 fallback URL
    hls: { type: String, default: null }, // master.m3u8 URL (opcionális)
    poster: { type: String, default: null },
    autoplay: { type: Boolean, default: false },
});

const video = ref(null);
let hlsInstance = null;

function nativeHls() {
    return video.value?.canPlayType('application/vnd.apple.mpegurl');
}

async function attach() {
    detach();
    const el = video.value;
    if (!el) return;

    if (props.hls && nativeHls()) {
        el.src = props.hls;
        return;
    }

    if (props.hls) {
        try {
            const { default: Hls } = await import('hls.js');
            if (Hls.isSupported()) {
                hlsInstance = new Hls({ capLevelToPlayerSize: true });
                hlsInstance.loadSource(props.hls);
                hlsInstance.attachMedia(el);
                hlsInstance.on(Hls.Events.ERROR, (_e, data) => {
                    if (data.fatal) {
                        detach();
                        el.src = props.src;
                    }
                });
                return;
            }
        } catch (e) {
            // hls.js betöltése nem sikerült — MP4 fallback
        }
    }

    el.src = props.src;
}

function detach() {
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }
}

onMounted(attach);
onBeforeUnmount(detach);
watch(() => [props.src, props.hls], attach);
</script>

<template>
    <video
        ref="video"
        class="max-h-full max-w-full rounded-[var(--radius-base)] bg-black"
        controls
        playsinline
        :autoplay="autoplay"
        :poster="poster || undefined"
    />
</template>
