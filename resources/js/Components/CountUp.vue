<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    // formázó fn (pl. Intl.NumberFormat().format)
    format: { type: Function, default: (n) => String(n) },
    duration: { type: Number, default: 900 },
    // false → azonnal a végérték (admin „számlálók" kapcsoló)
    active: { type: Boolean, default: true },
});

const el = ref(null);
const display = ref(props.format(0));
let raf = null;
let observer = null;

function motionOff() {
    return (
        document.documentElement.dataset.anim === 'off' ||
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    );
}

function run() {
    const start = performance.now();
    const from = 0;
    const to = props.value;

    function tick(now) {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
        display.value = props.format(Math.round(from + (to - from) * eased));
        if (t < 1) {
            raf = requestAnimationFrame(tick);
        }
    }
    raf = requestAnimationFrame(tick);
}

onMounted(() => {
    if (!props.active || motionOff() || props.value <= 0) {
        display.value = props.format(props.value);
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                run();
                observer.disconnect();
            }
        },
        { threshold: 0.4 },
    );
    observer.observe(el.value);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(raf);
    observer?.disconnect();
});
</script>

<template>
    <span ref="el">{{ display }}</span>
</template>
