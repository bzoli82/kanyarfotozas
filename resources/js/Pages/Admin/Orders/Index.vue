<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    orders: Object,
    filters: Object,
    statuses: { type: Array, default: () => [] },
});

const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

function apply() {
    router.get('/admin/orders', { ...form }, { preserveState: true, replace: true });
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

const statusLabel = {
    pending: 'Függőben',
    paid: 'Fizetve',
    failed: 'Sikertelen',
    refunded: 'Visszatérítve',
};

const statusCls = {
    pending: 'text-amber-400',
    paid: 'text-emerald-400',
    failed: 'text-muted',
    refunded: 'text-red-500',
};

function fmt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU', { dateStyle: 'short', timeStyle: 'short' }) : '';
}
</script>

<template>
    <Head title="Rendelések" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Rendelések</h1>

        <form class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Keresés (sorszám / e-mail / tranzakció)</span>
                <input v-model="form.q" type="text" placeholder="KAN-2026-000042" class="w-72 rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Állapot</span>
                <select v-model="form.status" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Mind</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel[s] ?? s }}</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">Szűrés</button>
        </form>

        <div class="mt-5 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3 text-left">Sorszám</th>
                        <th class="px-4 py-3 text-left">Vásárló</th>
                        <th class="px-4 py-3 text-left">Tételek</th>
                        <th class="px-4 py-3 text-right">Összeg</th>
                        <th class="px-4 py-3 text-left">Állapot</th>
                        <th class="px-4 py-3 text-left">Szolgáltató</th>
                        <th class="px-4 py-3 text-left">Dátum</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id" class="border-t border-border hover:bg-surface-1">
                        <td class="px-4 py-3"><Link :href="`/admin/orders/${o.id}`" class="font-semibold text-accent hover:underline">{{ o.order_number ?? `#${o.id}` }}</Link></td>
                        <td class="px-4 py-3 text-content">{{ o.buyer_email }}</td>
                        <td class="px-4 py-3 text-muted">{{ o.media_count }}</td>
                        <td class="px-4 py-3 text-right text-content">
                            {{ huf(o.total_cents) }}
                            <span v-if="o.refunded_cents > 0" class="block text-[11px] text-red-500">-{{ huf(o.refunded_cents) }}</span>
                        </td>
                        <td class="px-4 py-3 font-semibold" :class="statusCls[o.payment_status]">{{ statusLabel[o.payment_status] ?? o.payment_status }}</td>
                        <td class="px-4 py-3 text-muted">{{ o.payment_provider ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ fmt(o.created_at) }}</td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">Nincs a szűrésnek megfelelő rendelés.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="orders.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
            <Link
                v-for="(link, i) in orders.links"
                :key="i"
                :href="link.url ?? ''"
                v-html="link.label"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="[link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content', !link.url && 'pointer-events-none opacity-40']"
            />
        </div>
    </AdminLayout>
</template>
