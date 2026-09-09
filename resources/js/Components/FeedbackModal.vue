<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';

/**
 * Visszajelző modál — sikeres / sikertelen művelethez. Felül animált ikon
 * (zöld pipa vagy piros X), alatta a cím + üzenet(ek).
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    type: { type: String, default: 'success' }, // 'success' | 'error'
    title: { type: String, default: '' },
    // egy szöveg vagy szövegek listája (hiba-okok)
    message: { type: [String, Array], default: '' },
    closeLabel: { type: String, default: 'OK' },
});
const emit = defineEmits(['close']);

const messages = computed(() =>
    Array.isArray(props.message) ? props.message.filter(Boolean) : props.message ? [props.message] : [],
);

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            document.addEventListener('keydown', onKey);
            document.body.style.overflow = 'hidden';
        } else {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fm">
            <div
                v-if="open"
                class="fixed inset-0 z-[70] flex items-center justify-center p-4"
                role="alertdialog"
                aria-modal="true"
                @click.self="emit('close')"
            >
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                <div class="fm-panel relative w-full max-w-sm rounded-[var(--radius-base)] border border-border bg-surface-1 p-6 text-center shadow-2xl shadow-black/50">
                    <div class="mx-auto h-16 w-16">
                        <svg v-if="type === 'success'" viewBox="0 0 52 52" class="h-16 w-16">
                            <circle class="fm-ring" cx="26" cy="26" r="24" fill="none" stroke="#22c55e" stroke-width="3" />
                            <path class="fm-draw fm-check" d="M15 27l7.5 7.5L38 18" fill="none" stroke="#22c55e" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <svg v-else viewBox="0 0 52 52" class="h-16 w-16">
                            <circle class="fm-ring fm-ring--err" cx="26" cy="26" r="24" fill="none" stroke="#ef4444" stroke-width="3" />
                            <path class="fm-draw fm-x1" d="M18 18L34 34" stroke="#ef4444" stroke-width="4" stroke-linecap="round" />
                            <path class="fm-draw fm-x2" d="M34 18L18 34" stroke="#ef4444" stroke-width="4" stroke-linecap="round" />
                        </svg>
                    </div>

                    <h3 class="mt-4 text-base font-bold text-content">{{ title }}</h3>

                    <ul v-if="messages.length" class="mt-2 space-y-1 text-sm text-muted">
                        <li v-for="(m, i) in messages" :key="i">{{ m }}</li>
                    </ul>

                    <button
                        type="button"
                        class="mt-5 w-full rounded-[var(--radius-base)] bg-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                        @click="emit('close')"
                    >
                        {{ closeLabel }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fm-enter-active {
    transition: opacity 0.2s ease;
}
.fm-leave-active {
    transition: opacity 0.15s ease;
}
.fm-enter-from,
.fm-leave-to {
    opacity: 0;
}
.fm-enter-active .fm-panel {
    animation: fm-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes fm-pop {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: none;
        opacity: 1;
    }
}

/* ikon-rajz: a kör, majd a pipa / X „húzódik" */
.fm-ring {
    stroke-dasharray: 160;
    stroke-dashoffset: 160;
    animation: fm-stroke 0.5s ease forwards;
}
.fm-draw {
    stroke-dasharray: 60;
    stroke-dashoffset: 60;
    animation: fm-stroke 0.3s ease forwards;
}
.fm-check {
    animation-delay: 0.42s;
}
.fm-x1 {
    animation-delay: 0.42s;
}
.fm-x2 {
    animation-delay: 0.58s;
}
@keyframes fm-stroke {
    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fm-ring,
    .fm-draw {
        animation: none;
        stroke-dashoffset: 0;
    }
    .fm-enter-active .fm-panel {
        animation: none;
    }
}
</style>
