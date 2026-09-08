<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    filters: Object,
    sort: String,
    direction: String,
    summary: Object,
    rows: Object,
    photographers: Array,
    events: Array,
});

const form = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    photographer_id: props.filters.photographer_id ?? '',
    event_id: props.filters.event_id ?? '',
    type: props.filters.type ?? '',
});

function applyFilters() {
    router.get('/admin/stats', { ...form }, { preserveState: true });
}

function sortBy(column) {
    const direction = props.sort === column && props.direction === 'asc' ? 'desc' : 'asc';
    router.get('/admin/stats', { ...form, sort: column, direction }, { preserveState: true });
}

function exportUrl() {
    return '/admin/stats/export?' + new URLSearchParams(form).toString();
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}
</script>

<template>
    <Head title="Értékesítési statisztikák" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Értékesítési statisztikák</h1>
            <a :href="exportUrl()" class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent">
                CSV export
            </a>
        </div>

        <form class="mt-4 grid gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátumtól</span>
                <input v-model="form.date_from" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátumig</span>
                <input v-model="form.date_to" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                <select v-model="form.photographer_id" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes fotós</option>
                    <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Esemény</span>
                <select v-model="form.event_id" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes esemény</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Típus</span>
                <select v-model="form.type" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Kép és videó</option>
                    <option value="photo">Kép</option>
                    <option value="video">Videó</option>
                </select>
            </label>
            <div class="sm:col-span-2 lg:col-span-5">
                <button type="submit" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    Szűrés
                </button>
            </div>
        </form>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(summary.total_revenue_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Összes bevétel</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ summary.media_sold }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Eladott média</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(summary.average_order_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Átlagos rendelési érték</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ summary.unique_buyers }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Egyedi vásárló</div>
            </div>
        </div>
        <p v-if="summary.top_event" class="mt-2 text-xs text-muted">
            Legsikeresebb esemény: <span class="font-semibold text-content">{{ summary.top_event }}</span>
        </p>

        <div class="mt-6 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="cursor-pointer px-4 py-3" @click="sortBy('order_date')">Dátum</th>
                        <th class="cursor-pointer px-4 py-3" @click="sortBy('photographer_name')">Fotós</th>
                        <th class="cursor-pointer px-4 py-3" @click="sortBy('event_name')">Esemény</th>
                        <th class="px-4 py-3">Típus</th>
                        <th class="cursor-pointer px-4 py-3" @click="sortBy('price_cents')">Ár</th>
                        <th class="px-4 py-3">Vásárló</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="`${row.order_id}-${row.media_id}`" class="border-t border-border" :class="{ 'bg-accent/5': row.is_top_media }">
                        <td class="px-4 py-2.5 text-content">{{ new Date(row.order_date).toLocaleDateString('hu-HU') }}</td>
                        <td class="px-4 py-2.5 text-content">{{ row.photographer_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-content">{{ row.event_name }}</td>
                        <td class="px-4 py-2.5 text-muted">{{ row.media_type === 'video' ? 'Videó' : 'Kép' }}</td>
                        <td class="px-4 py-2.5 text-content">{{ huf(row.price_cents) }}</td>
                        <td class="px-4 py-2.5 text-muted">{{ row.buyer_email_masked }}</td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-muted">Nincs a szűrésnek megfelelő értékesítés.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rows.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in rows.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content'"
                preserve-state
            />
        </div>
    </AdminLayout>
</template>
