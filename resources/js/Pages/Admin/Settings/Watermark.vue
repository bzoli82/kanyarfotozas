<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    fonts: Array,
});

const densityOptions = [
    { value: 1, label: 'Ritkás' },
    { value: 2, label: 'Ritka' },
    { value: 3, label: 'Közepes' },
    { value: 4, label: 'Sűrű' },
    { value: 5, label: 'Nagyon sűrű' },
];

const form = useForm({
    text: props.settings.text,
    font: props.settings.font,
    size: props.settings.size,
    density: props.settings.density,
});

function save() {
    form.put('/admin/settings/watermark', {
        preserveScroll: true,
        onSuccess: () => refreshPreview(),
    });
}

// Elo elonezet: a meg el nem mentett urlap-ertekekkel keri le a mintakepet,
// hogy az admin lassa a hatast mentes elott is.
const previewUrl = ref(null);
const previewLoading = ref(false);
let previewObjectUrl = null;

async function refreshPreview() {
    previewLoading.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/admin/settings/watermark/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'image/webp',
            },
            body: JSON.stringify({
                text: form.text,
                font: form.font,
                size: form.size,
                density: form.density,
            }),
        });

        if (!res.ok) return;

        const blob = await res.blob();
        if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = URL.createObjectURL(blob);
        previewUrl.value = previewObjectUrl;
    } finally {
        previewLoading.value = false;
    }
}

let debounceTimer = null;
function debouncedRefreshPreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(refreshPreview, 400);
}

onMounted(refreshPreview);
onBeforeUnmount(() => {
    if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
    clearTimeout(debounceTimer);
});
</script>

<template>
    <Head title="Vízjel beállítások" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Vízjel beállítások</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Ez a vízjel jelenik meg csempézve a fotók és videók ingyenes, nyilvános előnézetén (EPIC-04/05 pipeline).
            A beállítás egyszerre vonatkozik a képekre és a videókra is.
        </p>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <form class="space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Vízjel szövege</span>
                    <input
                        v-model="form.text"
                        type="text"
                        maxlength="60"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        @input="debouncedRefreshPreview"
                    />
                    <p v-if="form.errors.text" class="mt-1 text-xs text-accent">{{ form.errors.text }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Betűtípus</span>
                    <select
                        v-model="form.font"
                        class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        @change="refreshPreview"
                    >
                        <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <span>Betűméret</span>
                        <span class="text-content">{{ form.size }} px</span>
                    </span>
                    <input
                        v-model.number="form.size"
                        type="range"
                        min="10"
                        max="60"
                        class="w-full accent-[var(--color-accent)]"
                        @change="refreshPreview"
                    />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szövegsűrűség</span>
                    <select
                        v-model.number="form.density"
                        class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        @change="refreshPreview"
                    >
                        <option v-for="d in densityOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Minél sűrűbb, annál kevesebb hely marad vágással eltávolítani a vízjelet.</p>
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                        Mentés
                    </button>
                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="refreshPreview">
                        Előnézet frissítése
                    </button>
                </div>
            </form>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Élő előnézet</h2>
                <p class="mt-1 text-xs text-muted">Minta-képen — a tényleges hatás a képek/videók tényleges tartalmán ettől eltérhet.</p>

                <div class="relative mt-4 aspect-[4/3] overflow-hidden rounded-lg border border-border bg-surface-2">
                    <img v-if="previewUrl" :src="previewUrl" alt="Vízjel előnézet" class="h-full w-full object-cover" />
                    <div v-if="previewLoading" class="absolute inset-0 grid place-items-center bg-surface-1/60 text-xs text-muted">
                        Frissítés…
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
