<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useChartColors } from '@/Composables/useChartColors';
import '@/chartSetup';

const props = defineProps({
    kpis: Object,
    charts: Object,
    latestEvents: Array,
    latestOrders: Array,
    security: Object,
    alerts: { type: Array, default: () => [] },
    onboarding: { type: Object, default: null },
    funnel: { type: Object, default: () => ({ stages: [], days: 30 }) },
    eventPerformance: { type: Array, default: () => [] },
    mediaHealth: { type: Object, default: () => ({ samples: [], plate_recognition: {} }) },
    forecast: { type: Object, default: () => ({ week: {}, month: {} }) },
    photographerComparison: { type: Array, default: () => [] },
    conversionWatch: { type: Array, default: () => [] },
});

// --- Proaktiv figyelmeztetesek ---
const severityStyle = {
    critical: 'border-accent bg-accent/10',
    warning: 'border-amber-500/50 bg-amber-500/10',
    info: 'border-border bg-surface-2',
};

function dismissAlert(key) {
    router.post('/admin/dashboard/alerts/dismiss', { key }, { preserveScroll: true });
}

function dismissOnboarding() {
    router.post('/admin/dashboard/onboarding/dismiss', {}, { preserveScroll: true });
}

// --- Mediaegeszseg ---
function reprocess(id) {
    router.post(`/admin/dashboard/media/${id}/reprocess`, {}, { preserveScroll: true });
}

// --- Idoszakos statisztika export ---
function isoMonth(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}
const now = new Date();
const exportForm = ref({
    from: isoMonth(new Date(now.getFullYear(), now.getMonth() - 11, 1)),
    to: isoMonth(now),
    granularity: 'month',
});
const exportUrl = computed(() => {
    const p = new URLSearchParams(exportForm.value);
    return `/admin/dashboard/stats/export?${p.toString()}`;
});

// --- Fotos osszehasonlitas: kliens-oldali rendezes ---
const sortKey = ref('revenue_cents');
const sortDir = ref('desc');

function sortBy(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

const sortedComparison = computed(() => {
    const rows = [...props.photographerComparison];
    const k = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return rows.sort((a, b) => {
        if (typeof a[k] === 'string') {
            return a[k].localeCompare(b[k]) * dir;
        }
        return (a[k] - b[k]) * dir;
    });
});

const { colors, palette } = useChartColors();

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

function nf(value) {
    return new Intl.NumberFormat('hu-HU').format(value ?? 0);
}

const chartBaseOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: colors.value.muted } },
    },
    scales: {
        x: { ticks: { color: colors.value.muted }, grid: { color: colors.value.border } },
        y: { ticks: { color: colors.value.muted }, grid: { color: colors.value.border } },
    },
}));

const noScaleOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: colors.value.muted } },
    },
}));

const revenueTrendData = computed(() => ({
    labels: props.charts.revenue_trend.labels,
    datasets: [{
        label: 'Bevétel (Ft)',
        data: props.charts.revenue_trend.data,
        borderColor: colors.value.accent,
        backgroundColor: colors.value.accent + '33',
        tension: 0.3,
        fill: true,
    }],
}));

const mediaTypeData = computed(() => ({
    labels: ['Kép', 'Videó'],
    datasets: [{
        data: [props.charts.media_type_split.photo, props.charts.media_type_split.video],
        backgroundColor: [palette.value[0], palette.value[1]],
    }],
}));

const topEventsData = computed(() => ({
    labels: props.charts.top_events.labels,
    datasets: [{ label: 'Bevétel (Ft)', data: props.charts.top_events.data, backgroundColor: colors.value.accent }],
}));

const topPhotographersData = computed(() => ({
    labels: props.charts.top_photographers.labels,
    datasets: [{ label: 'Bevétel (Ft)', data: props.charts.top_photographers.data, backgroundColor: palette.value[1] }],
}));

const statusLabels = { processing: 'Feldolgozás alatt', ready: 'Kész', failed: 'Hibás', hidden: 'Elrejtve' };
const processingStatusData = computed(() => {
    const entries = Object.entries(props.charts.processing_status);

    return {
        labels: entries.map(([key]) => statusLabels[key] ?? key),
        datasets: [{ data: entries.map(([, v]) => v), backgroundColor: palette.value }],
    };
});

const orderStatusLabels = { pending: 'Függőben', paid: 'Fizetve', failed: 'Sikertelen', refunded: 'Visszatérítve' };

function resendEmail(orderId) {
    router.post(`/admin/orders/${orderId}/resend-email`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="flex items-center justify-between">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Dashboard</h1>
            <Link href="/admin/events/create" class="rounded-lg bg-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                + Új esemény
            </Link>
        </div>

        <!-- „Első lépések" — élesítés előtti / utáni beállítási teendők -->
        <div v-if="onboarding" class="mt-4 rounded-[var(--radius-base)] border border-accent/40 bg-surface-1 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Első lépések</h2>
                    <p class="mt-0.5 text-xs text-muted">{{ onboarding.done }} / {{ onboarding.total }} kész — a hiányzókra kattintva egyből a beállításhoz jutsz.</p>
                </div>
                <button type="button" class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-content" @click="dismissOnboarding">Elrejtem</button>
            </div>

            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full bg-accent transition-all" :style="{ width: (onboarding.done / onboarding.total * 100) + '%' }" />
            </div>

            <ul class="mt-4 space-y-1.5">
                <li v-for="item in onboarding.items" :key="item.key">
                    <Link
                        :href="item.href"
                        class="flex items-start gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-surface-2"
                        :class="item.done ? 'text-muted' : 'text-content'"
                    >
                        <svg v-if="item.done" class="mt-0.5 shrink-0 text-accent" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg>
                        <span v-else class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full border-2 border-accent" />
                        <span class="min-w-0">
                            <span class="block text-sm" :class="{ 'line-through': item.done }">{{ item.label }}</span>
                            <span v-if="!item.done" class="block text-[11px] text-muted">{{ item.hint }}</span>
                        </span>
                    </Link>
                </li>
            </ul>
        </div>

        <div v-if="security.active" class="mt-4 rounded-[var(--radius-base)] border border-accent bg-accent/10 p-4 text-sm text-content">
            <strong class="text-accent">Biztonsági figyelmeztetés:</strong>
            {{ security.failed_logins_24h }} sikertelen bejelentkezési kísérlet az elmúlt 24 órában
            ({{ security.distinct_ips_24h }} különböző IP-ről).
        </div>

        <!-- Proaktiv figyelmeztetesek -->
        <div v-if="alerts.length" class="mt-4 space-y-2">
            <div
                v-for="alert in alerts"
                :key="alert.key"
                class="flex items-start gap-3 rounded-[var(--radius-base)] border p-4 text-sm text-content"
                :class="severityStyle[alert.severity] ?? severityStyle.info"
            >
                <span
                    class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                    :class="alert.severity === 'critical' ? 'bg-accent' : alert.severity === 'warning' ? 'bg-amber-500' : 'bg-muted'"
                />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ alert.title }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ alert.description }}</p>
                    <a v-if="alert.action_url" :href="alert.action_url" class="mt-1 inline-block text-xs font-semibold text-accent hover:text-accent-hover">
                        {{ alert.action_label }} →
                    </a>
                </div>
                <button type="button" class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-content" @click="dismissAlert(alert.key)">
                    Elrejtés
                </button>
            </div>
        </div>

        <!-- KPI kartyak -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ huf(kpis.revenue.today) }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Mai bevétel</div>
                <div class="mt-2 text-[11px] text-muted">Hónap: {{ huf(kpis.revenue.month) }} · Összes: {{ huf(kpis.revenue.all_time) }}</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.media_sold.today }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Ma eladott média</div>
                <div class="mt-2 text-[11px] text-muted">Hónap: {{ kpis.media_sold.month }} · Összes: {{ kpis.media_sold.all_time }}</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.active_orders_today }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Mai fizetett rendelés</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.active_download_tokens }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Aktív letöltési token</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.uploaded_media.today }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Ma feltöltött média</div>
                <div class="mt-2 text-[11px] text-muted">Hónap: {{ kpis.uploaded_media.month }}</div>
            </div>
            <div class="rounded-[var(--radius-base)] border p-5" :class="kpis.failed_media_count > 0 ? 'border-accent bg-accent/10' : 'border-border bg-surface-1'">
                <div class="text-2xl font-bold" :class="kpis.failed_media_count > 0 ? 'text-accent' : 'text-content'">{{ kpis.failed_media_count }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Hibás feldolgozás</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.photographers.active }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Aktív fotós</div>
                <div class="mt-2 text-[11px] text-muted">Inaktív: {{ kpis.photographers.inactive }}</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ kpis.subscribers_count }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Helyszín-feliratkozó</div>
                <div class="mt-2 text-[11px] text-muted">Esemény-értesítést kérő e-mail címek</div>
            </div>
        </div>

        <!-- Diagramok -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Bevételi trend (30 nap)</h2>
                <div class="mt-4 h-64"><Line :data="revenueTrendData" :options="chartBaseOptions" /></div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Értékesített média típusa</h2>
                <div class="mt-4 h-64"><Doughnut :data="mediaTypeData" :options="noScaleOptions" /></div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Top 5 esemény (bevétel)</h2>
                <div class="mt-4 h-64"><Bar :data="topEventsData" :options="chartBaseOptions" /></div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Top 5 fotós (bevétel)</h2>
                <div class="mt-4 h-64"><Bar :data="topPhotographersData" :options="chartBaseOptions" /></div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Feldolgozási státusz</h2>
                <div class="mx-auto mt-4 h-64 max-w-md"><Doughnut :data="processingStatusData" :options="noScaleOptions" /></div>
            </div>
        </div>

        <!-- Konverzios tolcser + bevetel-elorejelzes -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Konverziós tölcsér ({{ funnel.days }} nap)</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="stage in funnel.stages" :key="stage.key">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-content">{{ stage.label }}</span>
                            <span class="text-muted">{{ stage.count }} <span class="text-[11px]">({{ stage.pct_of_start }}%)</span></span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-accent" :style="{ width: Math.max(2, stage.pct_of_start) + '%' }" />
                        </div>
                        <p v-if="stage.key !== 'cart'" class="mt-0.5 text-[11px] text-muted">Előző lépésből: {{ stage.pct_of_prev }}%</p>
                    </div>
                </div>
                <p class="mt-4 text-[11px] text-muted">
                    A „Galéria megtekintés” a saját napi megtekintés-számlálóból jön (munkamenetenként egyszer), a többi lépcső a rendelések életciklusából. Külső analitika nem szükséges.
                </p>
            </div>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Bevétel-előrejelzés</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Ez a hét</div>
                        <div class="mt-1 text-2xl font-bold text-content">{{ huf(forecast.week.forecast_cents) }}</div>
                        <div class="mt-1 text-[11px] text-muted">Eddig: {{ huf(forecast.week.actual_cents) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Ez a hónap</div>
                        <div class="mt-1 text-2xl font-bold text-content">{{ huf(forecast.month.forecast_cents) }}</div>
                        <div class="mt-1 text-[11px] text-muted">Eddig: {{ huf(forecast.month.actual_cents) }}</div>
                    </div>
                </div>
                <p class="mt-4 text-[11px] text-muted">
                    Az elmúlt 30 nap napi bevételére illesztett lineáris trend alapján.
                    Napi változás: {{ huf(forecast.daily_slope_cents) }}/nap.
                </p>
            </div>
        </div>

        <!-- Esemeny-szintu teljesitmeny -->
        <div v-if="eventPerformance.length" class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Események teljesítménye (30 nap)</h2>
            <p class="mt-1 text-xs text-muted">A konverziós tölcsér eseményenként — melyik fotózás hoz megtekintést és bevételt. Konverzió = fizetett rendelés / galéria-megtekintés.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-xs">
                    <thead class="text-[10px] uppercase tracking-wide text-muted">
                        <tr class="border-b border-border">
                            <th class="py-2 pr-3 font-semibold">Esemény</th>
                            <th class="py-2 px-3 text-right font-semibold">Megtekintés</th>
                            <th class="py-2 px-3 text-right font-semibold">Rendelés</th>
                            <th class="py-2 px-3 text-right font-semibold">Fizetett</th>
                            <th class="py-2 px-3 text-right font-semibold">Bevétel</th>
                            <th class="py-2 pl-3 text-right font-semibold">Konverzió</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ev in eventPerformance" :key="ev.id" class="border-b border-border last:border-b-0">
                            <td class="py-2 pr-3">
                                <a :href="`/events/${ev.slug}`" target="_blank" class="font-medium text-content hover:text-accent">{{ ev.name }}</a>
                                <span v-if="ev.event_date" class="ml-1 text-[10px] text-muted">{{ ev.event_date }}</span>
                            </td>
                            <td class="py-2 px-3 text-right text-muted">{{ nf(ev.views) }}</td>
                            <td class="py-2 px-3 text-right text-muted">{{ nf(ev.orders) }}</td>
                            <td class="py-2 px-3 text-right text-content">{{ nf(ev.paid_orders) }}</td>
                            <td class="py-2 px-3 text-right font-semibold text-content">{{ huf(ev.revenue_cents) }}</td>
                            <td class="py-2 pl-3 text-right" :class="ev.conversion === null ? 'text-muted' : 'text-content'">
                                {{ ev.conversion === null ? '–' : ev.conversion + '%' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Idoszakos statisztika export -->
        <div class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Statisztika export</h2>
            <p class="mt-1 text-xs text-muted">Időszakos összesítő CSV — időszakonként egy sor (bevétel, eladott média kép/videó bontásban, rendelések, konverzió, feltöltések, aktív fotósok).</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Kezdő hónap</span>
                    <input v-model="exportForm.from" type="month" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Záró hónap</span>
                    <input v-model="exportForm.to" type="month" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Bontás</span>
                    <select v-model="exportForm.granularity" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="month">Havi</option>
                        <option value="week">Heti</option>
                    </select>
                </label>
                <a :href="exportUrl" class="rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    CSV letöltése
                </a>
            </div>
        </div>

        <!-- Mediaegeszseg panel -->
        <div id="media-health" class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Médiaegészség</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border p-3" :class="mediaHealth.failed > 0 ? 'border-accent bg-accent/10' : 'border-border'">
                    <div class="text-xl font-bold" :class="mediaHealth.failed > 0 ? 'text-accent' : 'text-content'">{{ mediaHealth.failed }}</div>
                    <div class="text-[11px] text-muted">Hibás feldolgozás</div>
                </div>
                <div class="rounded-lg border border-border p-3">
                    <div class="text-xl font-bold text-content">{{ mediaHealth.stuck_processing }}</div>
                    <div class="text-[11px] text-muted">Elakadt (&gt;2 óra)</div>
                </div>
                <div class="rounded-lg border border-border p-3">
                    <div class="text-xl font-bold text-content">{{ mediaHealth.videos_missing_sprite }}</div>
                    <div class="text-[11px] text-muted">Hiányzó scrub-sprite</div>
                </div>
                <div class="rounded-lg border border-border p-3">
                    <div class="text-xl font-bold text-content">{{ mediaHealth.ready_missing_variants }}</div>
                    <div class="text-[11px] text-muted">Hiányzó változat</div>
                </div>
                <div class="rounded-lg border border-border p-3">
                    <div class="text-xl font-bold text-content">
                        {{ mediaHealth.plate_recognition.enabled ? mediaHealth.plate_recognition.pending : '–' }}
                    </div>
                    <div class="text-[11px] text-muted">
                        {{ mediaHealth.plate_recognition.enabled ? 'Rendszám-feldolgozásra vár' : 'Rendszámfelismerés kikapcsolva' }}
                    </div>
                </div>
            </div>

            <div v-if="mediaHealth.plate_recognition.enabled && (mediaHealth.plate_recognition.unidentifiable || mediaHealth.plate_recognition.video_review)" class="mt-3 flex flex-wrap gap-4 text-[11px] text-muted">
                <span v-if="mediaHealth.plate_recognition.unidentifiable">
                    <span class="font-bold text-content">{{ mediaHealth.plate_recognition.unidentifiable }}</span> bizonytalan rendszám (kézi ellenőrzés)
                </span>
                <span v-if="mediaHealth.plate_recognition.video_review">
                    <span class="font-bold text-content">{{ mediaHealth.plate_recognition.video_review }}</span> videó rendszámmal — nincs auto-homályosítás, kézi ellenőrzés kell
                </span>
            </div>

            <div v-if="mediaHealth.samples.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted">
                            <th class="py-2 pr-3">Média</th>
                            <th class="py-2 pr-3">Esemény</th>
                            <th class="py-2 pr-3">Probléma</th>
                            <th class="py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in mediaHealth.samples" :key="s.id" class="border-b border-border last:border-b-0">
                            <td class="py-2 pr-3 text-content">#{{ s.id }} <span class="text-[11px] text-muted">{{ s.type === 'video' ? 'videó' : 'kép' }}</span></td>
                            <td class="py-2 pr-3 text-muted">{{ s.event ?? '—' }}</td>
                            <td class="py-2 pr-3 text-muted">{{ s.issue }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="reprocess(s.id)">
                                    Újrafeldolgozás
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-3 text-sm text-muted">Minden médiafájl rendben.</p>
        </div>

        <!-- Konverzió-figyelő (csak superadmin) — oldalon kívüli értékesítés lehetséges jelei -->
        <div v-if="conversionWatch.length" class="mt-6 rounded-[var(--radius-base)] border border-amber-500/50 bg-amber-500/10 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Fotós-figyelmeztetések</h2>
            <p class="mt-1 text-xs text-muted">
                Ezeknél a fotósoknál a konverzió elmarad az átlagtól, vagy sok a kérdés eladás nélkül.
                Lehet ártalmatlan (új fotós, gyenge esemény), de érdemes ránézni — előfordulhat oldalon kívüli értékesítés.
            </p>
            <ul class="mt-3 space-y-2">
                <li v-for="p in conversionWatch" :key="p.id" class="rounded-lg border border-border bg-surface-1 p-3 text-sm">
                    <a :href="`/admin/photographers/${p.id}`" class="font-semibold text-content hover:text-accent">{{ p.name }}</a>
                    <span class="ml-2 text-xs text-muted">{{ p.media_ready }} kész · {{ p.media_sold }} eladás · {{ p.inquiries }} kérdés</span>
                    <ul class="mt-1 list-disc pl-5 text-xs text-muted">
                        <li v-for="(r, i) in p.flag_reasons" :key="i">{{ r }}</li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Fotos osszehasonlito tablazat -->
        <div class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Fotós összehasonlítás</h2>
                <a href="/admin/dashboard/photographers/export" class="text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover">
                    CSV export
                </a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted">
                            <th v-for="col in [
                                { k: 'name', l: 'Fotós' },
                                { k: 'media_total', l: 'Feltöltött' },
                                { k: 'media_ready', l: 'Kész' },
                                { k: 'media_sold', l: 'Eladott' },
                                { k: 'conversion_rate', l: 'Konverzió %' },
                                { k: 'revenue_cents', l: 'Bevétel' },
                                { k: 'photographer_share_cents', l: 'Részesedés' },
                                { k: 'avg_price_cents', l: 'Átlagár' },
                            ]" :key="col.k" class="cursor-pointer select-none py-2 pr-3 hover:text-content" @click="sortBy(col.k)">
                                {{ col.l }}<span v-if="sortKey === col.k"> {{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sortedComparison" :key="row.id" class="border-b border-border last:border-b-0" :class="{ 'opacity-50': !row.is_active }">
                            <td class="py-2 pr-3 text-content">
                                <Link :href="`/admin/photographers/${row.id}`" class="hover:text-accent">{{ row.name }}</Link>
                            </td>
                            <td class="py-2 pr-3 text-muted">{{ row.media_total }}</td>
                            <td class="py-2 pr-3 text-muted">{{ row.media_ready }}</td>
                            <td class="py-2 pr-3 text-muted">{{ row.media_sold }}</td>
                            <td class="py-2 pr-3 text-muted">{{ row.conversion_rate }}%</td>
                            <td class="py-2 pr-3 text-content">{{ huf(row.revenue_cents) }}</td>
                            <td class="py-2 pr-3 text-muted">{{ huf(row.photographer_share_cents) }}</td>
                            <td class="py-2 text-muted">{{ huf(row.avg_price_cents) }}</td>
                        </tr>
                        <tr v-if="sortedComparison.length === 0">
                            <td colspan="8" class="py-3 text-sm text-muted">Még nincs fotós.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legfrissebb esemenyek + rendelesek -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Legfrissebb események</h2>
                <div class="mt-3 space-y-2">
                    <div v-for="event in latestEvents" :key="event.id" class="flex items-center justify-between border-b border-border py-2 text-sm last:border-b-0">
                        <div class="min-w-0">
                            <Link :href="`/admin/events/${event.id}`" class="truncate font-medium text-content hover:text-accent">{{ event.name }}</Link>
                            <p class="text-[11px] text-muted">{{ event.photographer }} · {{ event.media_count }} média</p>
                        </div>
                        <span class="shrink-0 text-[11px] uppercase tracking-wide text-muted">{{ event.status }}</span>
                    </div>
                    <p v-if="latestEvents.length === 0" class="text-sm text-muted">Még nincs esemény.</p>
                </div>
            </div>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Legfrissebb rendelések</h2>
                <div class="mt-3 space-y-2">
                    <div v-for="order in latestOrders" :key="order.id" class="flex items-center justify-between border-b border-border py-2 text-sm last:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-content">
                                <a v-if="order.order_number" :href="`/admin/orders/${order.id}`" class="text-accent hover:underline">{{ order.order_number }}</a>
                                <span class="text-muted"> · {{ order.email_masked }}</span>
                            </p>
                            <p class="text-[11px] text-muted">{{ huf(order.total_cents) }} · {{ order.media_count }} média · {{ orderStatusLabels[order.status] ?? order.status }}</p>
                        </div>
                        <button
                            v-if="order.status === 'paid'"
                            type="button"
                            class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover"
                            @click="resendEmail(order.id)"
                        >
                            Link újraküldés
                        </button>
                    </div>
                    <p v-if="latestOrders.length === 0" class="text-sm text-muted">Még nincs rendelés.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
