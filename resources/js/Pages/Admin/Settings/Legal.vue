<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    legal: { type: Object, required: true },
});

const form = useForm({
    impressum: props.legal.impressum,
    terms: props.legal.terms,
    photographer_agreement: props.legal.photographer_agreement,
});

function save() {
    form.put('/admin/settings/legal', { preserveScroll: true });
}
</script>

<template>
    <Head title="Jogi oldalak" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Jogi oldalak — Impresszum &amp; ÁSZF</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A tartalom <strong class="text-content">Markdown</strong> formátumban írható (## cím, **félkövér**, - lista, [link](/url)).
            A publikus oldalak: <a href="/impresszum" target="_blank" class="text-accent hover:underline">/impresszum</a> és
            <a href="/aszf" target="_blank" class="text-accent hover:underline">/aszf</a> — a láblécből is elérhetők.
        </p>

        <div class="mt-4 max-w-2xl rounded-[var(--radius-base)] border border-amber-500/40 bg-amber-500/10 p-4 text-xs text-amber-300">
            A magyar webshop-előírás szerint az <strong>Impresszum</strong> (Ekertv. 4. §) és az <strong>ÁSZF</strong> kötelező.
            A mezők most vázakat tartalmaznak <code>[kitöltendő]</code> jelölőkkel — az üzemeltető (szükség szerint ügyvéddel)
            véglegesíti. A pénztárban a vásárló egy kötelező pipával fogadja el az ÁSZF-et + az adatvédelmi tájékoztatót,
            és nyilatkozik az elállási jog megszűnéséről (azonnali digitális teljesítés).
        </div>

        <form class="mt-6 max-w-3xl space-y-6" @submit.prevent="save">
            <label class="block">
                <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                    <span>Impresszum</span>
                    <span v-if="legal.impressum_is_default" class="normal-case text-amber-400">alapértelmezett váz — töltsd ki</span>
                </span>
                <textarea v-model="form.impressum" rows="12" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 font-mono text-xs text-content focus:border-accent focus:outline-none"></textarea>
                <p v-if="form.errors.impressum" class="mt-1 text-xs text-red-500">{{ form.errors.impressum }}</p>
            </label>

            <label class="block">
                <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                    <span>Általános Szerződési Feltételek (ÁSZF)</span>
                    <span v-if="legal.terms_is_default" class="normal-case text-amber-400">alapértelmezett váz — töltsd ki</span>
                    <span v-else-if="legal.terms_updated_at" class="normal-case text-muted">utolsó módosítás: {{ legal.terms_updated_at }}</span>
                </span>
                <textarea v-model="form.terms" rows="20" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 font-mono text-xs text-content focus:border-accent focus:outline-none"></textarea>
                <p v-if="form.errors.terms" class="mt-1 text-xs text-red-500">{{ form.errors.terms }}</p>
            </label>

            <label class="block">
                <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                    <span>Fotós Megállapodás (a meghíváskor kötelező elfogadni)</span>
                    <span v-if="legal.photographer_agreement_is_default" class="normal-case text-amber-400">alapértelmezett váz — töltsd ki</span>
                </span>
                <textarea v-model="form.photographer_agreement" rows="18" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 font-mono text-xs text-content focus:border-accent focus:outline-none"></textarea>
                <p class="mt-1 text-[11px] text-muted">A meghívott fotós a fiók-aktiváláskor kötelező pipával fogadja el; a régi fotósok a dashboardjukon egy egyszeri gombbal. Tartalmazza az oldalon kívüli értékesítést tiltó záradékot.</p>
                <p v-if="form.errors.photographer_agreement" class="mt-1 text-xs text-red-500">{{ form.errors.photographer_agreement }}</p>
            </label>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Mentés
            </button>
        </form>
    </AdminLayout>
</template>
