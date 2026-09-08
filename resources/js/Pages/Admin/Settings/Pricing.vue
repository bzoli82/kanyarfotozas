<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    basePrice: { type: Number, default: 1490 },
    tiers: { type: Array, default: () => [] },
});

const form = useForm({
    base_price: props.basePrice,
    tiers: props.tiers.map((t) => ({ ...t })),
});

function addTier() {
    form.tiers.push({ min: 5, percent: 10 });
}

function removeTier(i) {
    form.tiers.splice(i, 1);
}

function submit() {
    form
        .transform((d) => ({
            ...d,
            tiers: [...d.tiers]
                .map((t) => ({ min: Number(t.min), percent: Number(t.percent) }))
                .filter((t) => t.min >= 2 && t.percent >= 1)
                .sort((a, b) => a.min - b.min),
        }))
        .put('/admin/settings/pricing', { preserveScroll: true });
}
</script>

<template>
    <Head title="Árazás" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Árazás</h1>

        <form class="mt-6 max-w-2xl space-y-8" @submit.prevent="submit">
            <!-- Alap ár -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Alap médiaár</h2>
                <p class="mt-1 text-xs text-muted">
                    Ez az alapértelmezés minden új eseményhez (Ft). Eseményenként felülírható a fotó / videó árral.
                </p>
                <label class="mt-3 block max-w-[12rem]">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Ár (Ft)</span>
                    <input v-model.number="form.base_price" type="number" min="0" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.base_price" class="mt-1 text-xs text-accent">{{ form.errors.base_price }}</p>
                </label>
            </div>

            <!-- Mennyiségi kedvezmény -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Automatikus mennyiségi kedvezmény</h2>
                <p class="mt-1 text-xs text-muted">
                    Kuponkód nélkül, automatikusan a kosárban: „N+ kép <strong class="text-content">egy eseményből</strong> → X% kedvezmény
                    annak az eseménynek a tételeire". A vásárló a kosárban látja („már csak 2 kép a 10%-hoz").
                    Több esemény esetén eseményenként külön számít. Kuponnal együtt is működik (a kupon a csökkentett részösszegre).
                    <strong class="text-content">Üres lista = kikapcsolva.</strong>
                </p>

                <div class="mt-4 space-y-2">
                    <div v-for="(tier, i) in form.tiers" :key="i" class="flex flex-wrap items-center gap-2 text-sm">
                        <label class="flex items-center gap-1.5 text-muted">
                            <input v-model.number="tier.min" type="number" min="2" class="w-20 rounded-lg border border-border bg-surface-2 px-2 py-1.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <span>db képtől</span>
                        </label>
                        <span class="text-muted">→</span>
                        <label class="flex items-center gap-1.5 text-muted">
                            <input v-model.number="tier.percent" type="number" min="1" max="90" class="w-20 rounded-lg border border-border bg-surface-2 px-2 py-1.5 text-sm text-content focus:border-accent focus:outline-none" />
                            <span>% kedvezmény</span>
                        </label>
                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-accent" @click="removeTier(i)">Törlés</button>
                    </div>
                </div>

                <button type="button" class="mt-3 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="addTier">
                    + Sáv hozzáadása
                </button>

                <p v-if="form.tiers.length === 0" class="mt-3 text-xs text-muted">Nincs sáv — a mennyiségi kedvezmény ki van kapcsolva.</p>
                <p class="mt-2 text-[11px] text-muted">Példa: <code>5 db → 10%</code>, <code>10 db → 15%</code>, <code>20 db → 20%</code>. A legmagasabb elért sáv számít.</p>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    {{ form.processing ? 'Mentés…' : 'Mentés' }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-xs font-semibold uppercase tracking-wide text-accent">Elmentve ✓</span>
            </div>
        </form>
    </AdminLayout>
</template>
