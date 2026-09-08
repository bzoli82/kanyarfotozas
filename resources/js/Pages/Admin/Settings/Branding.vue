<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    branding: Object,
    watermarkPreview: String,
});

const form = useForm({
    name: props.branding.name,
    logo_lead: props.branding.logo_lead,
    logo_tail: props.branding.logo_tail,
});

const watermarkLive = computed(() => (form.name || '').toUpperCase());

function save() {
    form.put('/admin/settings/branding', { preserveScroll: true });
}
</script>

<template>
    <Head title="Oldal neve" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Oldal neve / márkajel</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Ez a név jelenik meg a fejléc logóban, az oldalcímekben, az e-mailekben — és a
            <strong class="text-content">vízjelet is átírja</strong>. Bármikor módosítható, amíg a végleges név nincs eldöntve.
        </p>

        <form class="mt-6 max-w-xl space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Oldal neve</span>
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="60"
                    class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-accent">{{ form.errors.name }}</p>
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Logó — alap rész</span>
                    <input
                        v-model="form.logo_lead"
                        type="text"
                        maxlength="30"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.logo_lead" class="mt-1 text-xs text-accent">{{ form.errors.logo_lead }}</p>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Logó — akcent rész</span>
                    <input
                        v-model="form.logo_tail"
                        type="text"
                        maxlength="30"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p class="mt-1 text-[11px] text-muted">Üresen hagyva egyszínű a logó.</p>
                </label>
            </div>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-2 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Előnézet</p>
                <p class="font-display mt-2 text-lg font-bold tracking-tight text-content">
                    {{ form.logo_lead }}<span class="text-accent">{{ form.logo_tail }}</span>
                </p>
                <p class="mt-3 text-[11px] text-muted">Vízjel szövege: <span class="font-semibold text-content">{{ watermarkLive }}</span></p>
            </div>

            <div class="pt-1">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Mentés
                </button>
                <span class="ml-3 text-[11px] text-muted">A vízjel új felvételeknél lép életbe; a régiek újrafeldolgozással frissülnek.</span>
            </div>
        </form>
    </AdminLayout>
</template>
