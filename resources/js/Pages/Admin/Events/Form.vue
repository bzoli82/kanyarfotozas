<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FtpImportBrowser from '@/Components/FtpImportBrowser.vue';

const props = defineProps({
    countries: Array,
    basePrice: { type: Number, default: 1490 },
    photographers: { type: Array, default: () => [] },
    organizers: { type: Array, default: () => [] },
    ftpImport: { type: Object, default: null },
});

const form = useForm({
    country_id: '',
    name: '',
    location: '',
    latitude: '',
    longitude: '',
    event_date: '',
    starts_at: '',
    ends_at: '',
    status: 'draft',
    photo_price_cents: props.basePrice,
    video_price_cents: props.basePrice,
    organizer_id: '',
    organizer_share_percent: null,
    import_paths: [],
    import_photographer_id: props.photographers?.[0]?.id ?? '',
});

function submit() {
    form.post('/admin/events');
}
</script>

<template>
    <Head title="Új esemény" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Új esemény</h1>

        <form class="mt-6 max-w-2xl space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Esemény neve</span>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-accent">{{ form.errors.name }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Helyszín</span>
                    <input v-model="form.location" type="text" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.location" class="mt-1 text-xs text-accent">{{ form.errors.location }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Ország</span>
                    <select v-model="form.country_id" class="w-full appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="" disabled>Válassz országot</option>
                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.flag_emoji }} {{ c.name_hu }}</option>
                    </select>
                    <p v-if="form.errors.country_id" class="mt-1 text-xs text-accent">{{ form.errors.country_id }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Státusz</span>
                    <select v-model="form.status" class="w-full appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="draft">Vázlat</option>
                        <option value="announced">Meghirdetve</option>
                        <option value="live">Élő</option>
                        <option value="archived">Archivált</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szélesség (lat)</span>
                    <input v-model="form.latitude" type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.latitude" class="mt-1 text-xs text-accent">{{ form.errors.latitude }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Hosszúság (lon)</span>
                    <input v-model="form.longitude" type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.longitude" class="mt-1 text-xs text-accent">{{ form.errors.longitude }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátum</span>
                    <input v-model="form.event_date" type="date" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.event_date" class="mt-1 text-xs text-accent">{{ form.errors.event_date }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Kezdés</span>
                    <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.starts_at" class="mt-1 text-xs text-accent">{{ form.errors.starts_at }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Befejezés (opcionális)</span>
                    <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.ends_at" class="mt-1 text-xs text-accent">{{ form.errors.ends_at }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotó ára (Ft)</span>
                    <input v-model.number="form.photo_price_cents" type="number" min="0" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.photo_price_cents" class="mt-1 text-xs text-accent">{{ form.errors.photo_price_cents }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Videó ára (Ft)</span>
                    <input v-model.number="form.video_price_cents" type="number" min="0" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.video_price_cents" class="mt-1 text-xs text-accent">{{ form.errors.video_price_cents }}</p>
                </label>
            </div>
            <p class="text-xs text-muted">Az ár az esemény összes fotójára, illetve összes videójára vonatkozik — külön-külön nem árazunk. Később itt módosítható.</p>

            <div v-if="organizers.length" class="grid gap-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szervező (opcionális)</span>
                    <select v-model="form.organizer_id" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="">— nincs —</option>
                        <option v-for="o in organizers" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szervező részesedése (%)</span>
                    <input v-model.number="form.organizer_share_percent" type="number" min="0" max="100" :disabled="!form.organizer_id" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none disabled:opacity-50" />
                    <p v-if="form.errors.organizer_share_percent" class="mt-1 text-xs text-accent">{{ form.errors.organizer_share_percent }}</p>
                </label>
            </div>

            <!-- Opcionalis: kepek beolvasasa FTP-rol mindjart a letrehozaskor -->
            <FtpImportBrowser
                v-if="ftpImport"
                :available="ftpImport.available"
                :photographers="photographers"
                v-model:paths="form.import_paths"
                v-model:photographer-id="form.import_photographer_id"
            />
            <p v-if="form.errors.import_paths" class="text-xs text-accent">{{ form.errors.import_paths }}</p>
            <p v-if="form.errors.import_photographer_id" class="text-xs text-accent">{{ form.errors.import_photographer_id }}</p>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                <template v-if="form.import_paths.length > 0">Létrehozás + {{ form.import_paths.length }} kép importálása</template>
                <template v-else>Létrehozás</template>
            </button>
            <p class="text-xs text-muted">A létrehozás után ugyanezen az oldalon tudsz további képeket / videókat feltölteni és az adatokat módosítani.</p>
        </form>
    </AdminLayout>
</template>
