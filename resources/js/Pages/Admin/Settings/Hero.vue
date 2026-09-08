<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    slides: Array,
    recommended: Object,
});

const fileInput = ref(null);

const uploadForm = useForm({
    file: null,
});

function onFileChange(event) {
    uploadForm.file = event.target.files[0] ?? null;
    if (uploadForm.file) {
        submitUpload();
    }
}

function submitUpload() {
    uploadForm.post('/admin/settings/hero', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function toggleActive(slide) {
    router.put(`/admin/settings/hero/${slide.id}`, { is_active: !slide.is_active }, { preserveScroll: true });
}

function remove(slide) {
    if (window.confirm('Biztosan törlöd ezt a hero elemet?')) {
        router.delete(`/admin/settings/hero/${slide.id}`, { preserveScroll: true });
    }
}

function move(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= props.slides.length) {
        return;
    }
    const ids = props.slides.map((s) => s.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.put('/admin/settings/hero/reorder', { ids }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Hero média" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Hero média</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Ezek a képek és videók jelennek meg a főoldal fejlécében, egymásba áttűnve. Ha nincs feltöltött elem, a
            főoldal a beépített sötét háttérrel jelenik meg.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-4 text-sm text-content">
                <p class="font-semibold">Kép — ajánlott: {{ recommended.image.width }} × {{ recommended.image.height }} px</p>
                <p class="mt-1 text-xs text-muted">
                    16:9, fekvő. JPEG, PNG vagy WebP, max {{ recommended.image.max_mb }} MB. A rendszer automatikusan
                    max {{ recommended.image.width }} px széles, optimalizált WebP-re konvertálja.
                </p>
            </div>
            <div class="rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-4 text-sm text-content">
                <p class="font-semibold">Videó — ajánlott: {{ recommended.video.width }} × {{ recommended.video.height }} px (Full HD)</p>
                <p class="mt-1 text-xs text-muted">
                    16:9, fekvő. MP4 (H.264), max {{ recommended.video.bitrate_mbps }} Mbps bitráta,
                    {{ recommended.video.seconds }} mp körüli, végtelenítve vágható klip, max {{ recommended.video.max_mb }} MB.
                    Hang nem kell. A gyors oldalbetöltésért a rendszer újrakódolja max {{ recommended.video.width }} px
                    szélességre, ~4 Mbps-re, hang nélkül, és egy poszterképet is készít.
                </p>
            </div>
        </div>

        <div class="mt-6">
            <label
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                :class="{ 'pointer-events-none opacity-60': uploadForm.processing }"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" /></svg>
                {{ uploadForm.processing ? 'Feldolgozás…' : 'Kép vagy videó hozzáadása' }}
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" class="hidden" @change="onFileChange" />
            </label>
            <p v-if="uploadForm.processing" class="mt-2 text-xs text-muted">Videónál ez eltarthat pár másodpercig (újrakódolás).</p>
            <p v-if="uploadForm.errors.file" class="mt-2 text-xs text-accent">{{ uploadForm.errors.file }}</p>
        </div>

        <div v-if="slides.length === 0" class="mt-6 rounded-[var(--radius-base)] border border-dashed border-border p-8 text-center text-sm text-muted">
            Még nincs feltöltött hero elem.
        </div>

        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(slide, i) in slides"
                :key="slide.id"
                class="overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1"
                :class="{ 'opacity-50': !slide.is_active }"
            >
                <div class="relative aspect-video bg-surface-2">
                    <video
                        v-if="slide.type === 'video'"
                        :src="slide.url"
                        :poster="slide.poster_url || undefined"
                        muted
                        loop
                        playsinline
                        controls
                        class="h-full w-full object-cover"
                    />
                    <img v-else :src="slide.url" :alt="slide.original_filename" class="h-full w-full object-cover" />
                    <span class="absolute left-2 top-2 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold text-white/90">
                        #{{ i + 1 }}
                    </span>
                    <span class="absolute right-2 top-2 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white/90">
                        {{ slide.type === 'video' ? 'Videó' : 'Kép' }}
                    </span>
                </div>
                <div class="space-y-2 p-3">
                    <p class="truncate text-xs text-muted" :title="slide.original_filename">{{ slide.original_filename }}</p>
                    <p class="text-[11px] text-muted">
                        {{ slide.width }} × {{ slide.height }} px<span v-if="slide.duration_seconds"> · {{ slide.duration_seconds }} mp</span>
                    </p>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-content hover:border-accent disabled:opacity-40"
                            :disabled="i === 0"
                            @click="move(i, -1)"
                        >
                            ↑
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-content hover:border-accent disabled:opacity-40"
                            :disabled="i === slides.length - 1"
                            @click="move(i, 1)"
                        >
                            ↓
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-2 py-1 text-[11px] font-semibold"
                            :class="slide.is_active ? 'border-accent text-accent' : 'border-border text-muted'"
                            @click="toggleActive(slide)"
                        >
                            {{ slide.is_active ? 'Aktív' : 'Inaktív' }}
                        </button>
                        <button
                            type="button"
                            class="ml-auto rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-muted hover:border-accent hover:text-accent"
                            @click="remove(slide)"
                        >
                            Törlés
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
