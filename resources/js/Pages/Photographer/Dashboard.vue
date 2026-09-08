<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
    reportPrefs: Object,
    earnings: Object,
});

const prefs = reactive({ ...props.reportPrefs });

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

function dt(iso) {
    return iso ? new Date(iso).toLocaleDateString('hu-HU') : '—';
}

function saveReports() {
    router.put('/photographer/settings/reports', prefs, { preserveScroll: true });
}
</script>

<template>
    <Head title="Fotós Dashboard" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Dashboard</h1>
            <Link href="/admin/events/create" class="rounded-lg bg-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                + Új esemény
            </Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ stats.media_total }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Feltöltött média</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-accent">{{ stats.media_ready }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Kész</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ stats.media_processing }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Feldolgozás alatt</div>
            </div>
            <div class="rounded-[var(--radius-base)] border p-5" :class="stats.media_failed > 0 ? 'border-accent bg-accent/10' : 'border-border bg-surface-1'">
                <div class="text-2xl font-bold" :class="stats.media_failed > 0 ? 'text-accent' : 'text-content'">{{ stats.media_failed }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Hibás</div>
            </div>
        </div>

        <div v-if="earnings" class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Elszámolás</h2>
                <span class="text-xs text-muted">Jutalék: {{ earnings.revenue_share_percent }}% az eladási árból</span>
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-2xl font-bold text-accent">{{ huf(earnings.outstanding_cents) }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted">Kifizetésre vár ({{ earnings.outstanding_count }} tétel)</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-content">{{ huf(earnings.total_paid_cents) }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted">Eddig kifizetve</div>
                </div>
            </div>
            <div v-if="earnings.payouts.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[10px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="py-1.5 pr-3 font-semibold">Bizonylat</th>
                            <th class="py-1.5 pr-3 font-semibold">Összeg</th>
                            <th class="py-1.5 pr-3 font-semibold">Tétel</th>
                            <th class="py-1.5 pr-3 font-semibold">Állapot</th>
                            <th class="py-1.5 pr-3 font-semibold">Dátum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="p in earnings.payouts" :key="p.payout_number">
                            <td class="py-1.5 pr-3 font-mono text-content">{{ p.payout_number }}</td>
                            <td class="py-1.5 pr-3 text-content">{{ huf(p.amount_cents) }}</td>
                            <td class="py-1.5 pr-3 text-muted">{{ p.media_count }}</td>
                            <td class="py-1.5 pr-3" :class="p.status === 'paid' ? 'text-emerald-400' : 'text-amber-400'">
                                {{ p.status === 'paid' ? 'Kifizetve' : 'Előkészítve' }}
                            </td>
                            <td class="py-1.5 pr-3 text-muted">{{ dt(p.paid_at || p.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-[11px] text-muted">A kifizetéseket az üzemeltető indítja; kérdés esetén keresd őket.</p>
        </div>

        <div class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">E-mail riportok</h2>
            <p class="mt-1 text-xs text-muted">Automatikus értékesítési összesítő az általad feltöltött médiák eladásairól.</p>
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-sm text-content">
                    <input v-model="prefs.report_weekly" type="checkbox" class="accent-[var(--color-accent)]" @change="saveReports" />
                    Heti riport (hétfőnként)
                </label>
                <label class="flex items-center gap-2 text-sm text-content">
                    <input v-model="prefs.report_monthly" type="checkbox" class="accent-[var(--color-accent)]" @change="saveReports" />
                    Havi riport (minden hónap 1-jén, CSV melléklettel)
                </label>
            </div>
        </div>

        <Link href="/admin/events" class="mt-8 inline-block text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover">
            Eseményeim megtekintése →
        </Link>
    </AdminLayout>
</template>
