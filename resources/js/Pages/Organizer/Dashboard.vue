<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    events: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    payouts: { type: Array, default: () => [] },
});

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

const statusLabel = { draft: 'Vázlat', announced: 'Meghirdetve', live: 'Élő', archived: 'Archivált' };
</script>

<template>
    <Head title="Áttekintő" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Áttekintő</h1>
        <p class="mt-1 text-sm text-muted">A szervezésedben zajló eseményekből származó bevétel és a részesedésed.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(totals.gross_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Összes bruttó eladás</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(totals.share_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Részesedésed (összesen)</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(totals.paid_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Eddig kifizetve</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold" :class="totals.outstanding_cents > 0 ? 'text-accent' : 'text-content'">{{ huf(totals.outstanding_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Nyitott egyenleg</div>
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-content">Eseményeid</h2>
        <div class="mt-3 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Esemény</th>
                        <th class="px-4 py-3 font-semibold">Dátum</th>
                        <th class="px-4 py-3 font-semibold">Állapot</th>
                        <th class="px-4 py-3 font-semibold">Bruttó</th>
                        <th class="px-4 py-3 font-semibold">Vásárló</th>
                        <th class="px-4 py-3 font-semibold">Részesedés</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="ev in events" :key="ev.id" class="hover:bg-surface-1">
                        <td class="px-4 py-3">
                            <Link :href="`/organizer/events/${ev.id}`" class="font-medium text-content hover:text-accent">{{ ev.name }}</Link>
                            <div class="text-xs text-muted">{{ ev.location }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ ev.event_date }}</td>
                        <td class="px-4 py-3 text-muted">{{ statusLabel[ev.status] ?? ev.status }}</td>
                        <td class="px-4 py-3 text-muted">{{ huf(ev.gross_cents) }}</td>
                        <td class="px-4 py-3 text-muted">{{ ev.buyers }}</td>
                        <td class="px-4 py-3 text-content">{{ huf(ev.share_cents) }} <span class="text-[11px] text-muted">({{ ev.share_percent }}%)</span></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/organizer/events/${ev.id}`" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">Részletek</Link>
                        </td>
                    </tr>
                    <tr v-if="events.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">Még nincs hozzád rendelt esemény.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="events.length" class="mt-3">
            <a href="/organizer/dashboard/export" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover">CSV letöltése</a>
        </div>

        <template v-if="payouts.length">
            <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-content">Kifizetéseid</h2>
            <div class="mt-3 space-y-2">
                <div v-for="(p, i) in payouts" :key="i" class="flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-2.5 text-sm">
                    <span class="text-muted">{{ p.paid_at }}<template v-if="p.event"> · {{ p.event }}</template></span>
                    <span class="font-semibold text-content">{{ huf(p.amount_cents) }}</span>
                    <span v-if="p.reference" class="text-xs text-muted">{{ p.reference }}</span>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>
