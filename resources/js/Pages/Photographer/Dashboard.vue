<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
    reportPrefs: Object,
    earnings: Object,
    commissionBonus: { type: Object, default: () => ({ enabled: false }) },
    agreementPending: { type: Boolean, default: false },
    agreementHtml: { type: String, default: null },
});

const prefs = reactive({ ...props.reportPrefs });

const agreementForm = useForm({ accepted: false });
const agreementOpen = ref(false);

function acceptAgreement() {
    agreementForm.post('/profil/megallapodas', { preserveScroll: true });
}

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

        <!-- Fotós Megállapodás — egyszeri elfogadás a régi fotósoknak -->
        <div v-if="agreementPending && agreementHtml" class="mt-4 rounded-[var(--radius-base)] border-2 border-accent/50 bg-accent/5 p-4">
            <p class="text-sm font-semibold text-content">Kérjük, fogadd el a Fotós Megállapodást</p>
            <p class="mt-1 text-xs text-muted">Ez rögzíti az elszámolást és tartalmazza az oldalon kívüli értékesítést tiltó záradékot. Egyszeri elfogadás.</p>
            <button type="button" class="mt-2 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="agreementOpen = !agreementOpen">
                {{ agreementOpen ? 'Elrejtés' : 'Megállapodás elolvasása' }}
            </button>
            <div
                v-if="agreementOpen"
                class="mt-2 max-h-72 space-y-2 overflow-y-auto rounded-lg border border-border bg-surface-2 p-3 text-xs leading-relaxed text-muted [&_h2]:mt-2 [&_h2]:text-sm [&_h2]:font-bold [&_h2]:text-content [&_h3]:mt-2 [&_h3]:font-semibold [&_h3]:text-content [&_li]:ml-4 [&_li]:list-disc [&_p]:mt-1"
                v-html="agreementHtml"
            ></div>
            <label class="mt-3 flex gap-2 text-xs text-content">
                <input v-model="agreementForm.accepted" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                <span>Elolvastam és elfogadom a Fotós Megállapodást.</span>
            </label>
            <button
                type="button"
                :disabled="!agreementForm.accepted || agreementForm.processing"
                class="mt-3 rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
                @click="acceptAgreement"
            >
                Elfogadom
            </button>
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

            <div v-if="commissionBonus.enabled" class="mt-3 rounded-lg border border-accent/40 bg-accent/5 p-3 text-xs text-content">
                <span class="font-semibold">Havi volumen-bónusz:</span>
                ebben a hónapban <strong>{{ commissionBonus.sales_this_month }}</strong> eladásod van —
                a jelenlegi kulcsod <strong>{{ commissionBonus.current_percent }}%</strong>.
                <template v-if="commissionBonus.next">
                    Még <strong>{{ commissionBonus.next.needed }}</strong> eladás, és a következő eladásaidtól
                    <strong>{{ Math.min(95, commissionBonus.base_percent + commissionBonus.next.bonus_percent) }}%</strong>-ot kapsz.
                </template>
                <template v-else>Elérted a legmagasabb sávot. 🎉</template>
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
