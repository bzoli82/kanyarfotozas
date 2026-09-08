<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useMobileUpload, readVideoDuration } from '@/Composables/useMobileUpload';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    photographers: { type: Array, default: () => [] },
    maxFileMb: { type: Number, default: 500 },
    maxVideoSeconds: { type: Number, default: 300 },
});

const { jobs, online, queuedCount, addFiles, drainQueue, refreshQueuedCount } = useMobileUpload();

const eventId = ref(props.events[0]?.id ?? null);
const photographerId = ref(null);
const fileInput = ref(null);
const rejected = ref([]);

const statusLabels = {
    live: 'Élő', announced: 'Hamarosan', draft: 'Piszkozat',
};

const overallProgress = computed(() => {
    if (jobs.length === 0) return 0;
    return Math.round(jobs.reduce((s, j) => s + j.progress, 0) / jobs.length);
});
const activeCount = computed(() => jobs.filter((j) => j.status === 'uploading' || j.status === 'queued').length);
const doneCount = computed(() => jobs.filter((j) => j.status === 'done').length);

async function onPick(event) {
    rejected.value = [];
    const files = Array.from(event.target.files ?? []);
    const accepted = [];

    for (const file of files) {
        if (file.size > props.maxFileMb * 1024 * 1024) {
            rejected.value.push(`${file.name} — túl nagy (max ${props.maxFileMb} MB)`);
            continue;
        }
        if (file.type.startsWith('video/')) {
            const duration = await readVideoDuration(file);
            if (duration > props.maxVideoSeconds + 1) {
                rejected.value.push(`${file.name} — túl hosszú videó (max ${Math.round(props.maxVideoSeconds / 60)} perc)`);
                continue;
            }
        }
        accepted.push(file);
    }

    if (accepted.length && eventId.value) {
        await addFiles(accepted, { eventId: eventId.value, photographerId: photographerId.value });
        await refreshQueuedCount();
    }

    if (fileInput.value) fileInput.value.value = '';
}

function fmtSize(bytes) {
    if (!bytes) return '';
    const mb = bytes / 1024 / 1024;
    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <Head title="Feltöltés" />

    <div class="min-h-screen bg-surface-0 text-content">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-surface-1 px-4 py-3">
            <Link href="/" class="font-display text-sm font-bold tracking-tight"><BrandLogo /></Link>
            <span
                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="online ? 'bg-surface-2 text-muted' : 'bg-accent/20 text-accent'"
            >
                {{ online ? 'Online' : 'Offline' }}
            </span>
        </header>

        <main class="mx-auto max-w-lg space-y-5 px-4 py-6">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight">Feltöltés</h1>

            <div v-if="!online || queuedCount > 0" class="rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-3 text-sm">
                <p v-if="!online">Nincs net — a kiválasztott fájlok a sorba kerülnek, és automatikusan feltöltődnek, amint újra online vagy.</p>
                <p v-else>{{ queuedCount }} fájl vár a sorban.</p>
                <button
                    v-if="online && queuedCount > 0"
                    type="button"
                    class="mt-2 rounded-lg bg-accent px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white"
                    @click="drainQueue().then(refreshQueuedCount)"
                >
                    Sor feldolgozása most
                </button>
            </div>

            <div v-if="events.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border p-6 text-center text-sm text-muted">
                Nincs esemény, amihez feltölthetnél. Előbb hozz létre egyet a webes felületen.
            </div>

            <template v-else>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Esemény</span>
                    <select v-model="eventId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-3 text-base text-content focus:border-accent focus:outline-none">
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">
                            {{ ev.name }} · {{ ev.location }} ({{ statusLabels[ev.status] ?? ev.status }})
                        </option>
                    </select>
                </label>

                <label v-if="isAdmin" class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                    <select v-model="photographerId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-3 text-base text-content focus:border-accent focus:outline-none">
                        <option :value="null">— válassz —</option>
                        <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>

                <label
                    class="flex cursor-pointer flex-col items-center gap-2 rounded-[var(--radius-base)] border-2 border-dashed border-border bg-surface-1 p-8 text-center"
                    :class="{ 'pointer-events-none opacity-50': isAdmin && !photographerId }"
                >
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-accent"><path d="M12 16V4M6 10l6-6 6 6M4 20h16" /></svg>
                    <span class="text-sm font-semibold">Fotó vagy videó kiválasztása</span>
                    <span class="text-[11px] text-muted">Kép, vagy max {{ Math.round(maxVideoSeconds / 60) }} perces videó · a kamera is használható</span>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,video/mp4,video/quicktime"
                        multiple
                        capture="environment"
                        class="hidden"
                        @change="onPick"
                    />
                </label>

                <ul v-if="rejected.length" class="space-y-1 text-xs text-accent">
                    <li v-for="(r, i) in rejected" :key="i">{{ r }}</li>
                </ul>
            </template>

            <!-- Folyamat -->
            <div v-if="jobs.length" class="space-y-3">
                <div class="flex items-center justify-between text-xs text-muted">
                    <span>{{ doneCount }} / {{ jobs.length }} kész<span v-if="activeCount"> · {{ activeCount }} folyamatban</span></span>
                    <span>{{ overallProgress }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-surface-2">
                    <div class="h-full rounded-full bg-accent transition-all" :style="{ width: overallProgress + '%' }" />
                </div>

                <ul class="divide-y divide-border rounded-[var(--radius-base)] border border-border">
                    <li v-for="job in jobs" :key="job.id" class="flex items-center gap-3 p-3">
                        <span
                            class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                            :class="{
                                'bg-accent text-white': job.status === 'done',
                                'bg-surface-2 text-muted': job.status === 'uploading' || job.status === 'queued',
                                'bg-accent/20 text-accent': job.status === 'error',
                            }"
                        >
                            <svg v-if="job.status === 'done'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5" /></svg>
                            <span v-else-if="job.status === 'error'">!</span>
                            <span v-else-if="job.status === 'queued'">⏳</span>
                            <span v-else>{{ job.progress }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ job.name }}</p>
                            <p class="text-[11px] text-muted">
                                <span v-if="job.status === 'error'" class="text-accent">{{ job.error }}</span>
                                <span v-else-if="job.status === 'queued'">Sorban — net visszatérésekor folytatódik</span>
                                <span v-else-if="job.status === 'done'">Feltöltve · feldolgozás folyamatban</span>
                                <span v-else>{{ job.progress }}% · {{ fmtSize(job.size) }}</span>
                            </p>
                            <div v-if="job.status === 'uploading'" class="mt-1 h-1 overflow-hidden rounded-full bg-surface-2">
                                <div class="h-full bg-accent" :style="{ width: job.progress + '%' }" />
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="pt-2 text-center text-[11px] text-muted">
                Telepítsd az alkalmazást a böngésző menüjéből („Hozzáadás a kezdőképernyőhöz”) a gyorsabb hozzáféréshez.
            </p>
        </main>
    </div>
</template>
