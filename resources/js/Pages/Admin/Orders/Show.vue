<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ order: Object, invoicing: { type: Object, default: () => ({ configured: false, invoices: [] }) } });

const orderRef = computed(() => props.order.order_number ?? `#${props.order.id}`);
const hasIssuedInvoice = computed(() => props.invoicing.invoices.some((i) => i.type === 'normal' && i.status === 'issued'));

function issueInvoice() {
    router.post(`/admin/orders/${props.order.id}/invoice`, {}, { preserveScroll: true });
}
function stornoInvoice(id) {
    router.post(`/admin/invoices/${id}/storno`, {}, { preserveScroll: true });
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}
function fmt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

const refundable = computed(() => props.order.total_cents - props.order.refunded_cents);
const refundOpen = ref(false);
const refundForm = useForm({ amount_cents: null, reason: '' });

function submitRefund() {
    refundForm
        .transform((d) => ({ ...d, amount_cents: d.amount_cents ? Number(d.amount_cents) : null }))
        .post(`/admin/orders/${props.order.id}/refund`, {
            preserveScroll: true,
            onSuccess: () => {
                refundOpen.value = false;
                refundForm.reset();
            },
        });
}

function resend() {
    router.post(`/admin/orders/${props.order.id}/resend`, {}, { preserveScroll: true });
}

const statusLabel = { pending: 'Függőben', paid: 'Fizetve', failed: 'Sikertelen', refunded: 'Visszatérítve' };
</script>

<template>
    <Head :title="`Rendelés ${orderRef}`" />

    <AdminLayout>
        <div class="flex items-center gap-3">
            <Link href="/admin/orders" class="text-xs text-muted hover:text-content">← Rendelések</Link>
        </div>
        <h1 class="font-display mt-1 text-xl font-bold uppercase tracking-tight text-content">Rendelés {{ orderRef }}</h1>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Tételek</h2>
                <table class="mt-3 w-full text-sm">
                    <tbody>
                        <tr v-for="it in order.items" :key="it.id" class="border-t border-border first:border-0">
                            <td class="py-2 text-content">{{ it.event ?? 'Média' }} <span class="text-[11px] text-muted">#{{ it.id }} · {{ it.type }}</span></td>
                            <td class="py-2 text-right text-content">{{ huf(it.price_cents) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-border text-sm">
                        <tr v-if="order.discount_cents > 0"><td class="py-2 text-accent">Kedvezmény{{ order.coupon ? ` (${order.coupon})` : '' }}</td><td class="py-2 text-right text-accent">-{{ huf(order.discount_cents) }}</td></tr>
                        <tr><td class="py-2 font-semibold text-content">Végösszeg</td><td class="py-2 text-right font-bold text-content">{{ huf(order.total_cents) }}</td></tr>
                        <tr v-if="order.refunded_cents > 0"><td class="py-2 text-red-500">Visszatérítve</td><td class="py-2 text-right text-red-500">-{{ huf(order.refunded_cents) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-4">
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm">
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-muted">Vásárló</dt><dd class="text-content">{{ order.buyer_email }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Állapot</dt><dd class="font-semibold text-content">{{ statusLabel[order.payment_status] ?? order.payment_status }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Szolgáltató</dt><dd class="text-content">{{ order.payment_provider ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-muted">Tranzakció</dt><dd class="truncate text-right text-[11px] text-muted">{{ order.payment_provider_reference ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Létrehozva</dt><dd class="text-content">{{ fmt(order.created_at) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Letöltő link</dt><dd class="text-content">{{ order.download_token_uses }}× megnyitva</dd></div>
                        <div v-if="order.refunded_at" class="flex justify-between"><dt class="text-muted">Visszatérítve</dt><dd class="text-content">{{ fmt(order.refunded_at) }}</dd></div>
                        <div v-if="order.refund_reason" class="flex justify-between gap-2"><dt class="text-muted">Indok</dt><dd class="text-right text-[11px] text-muted">{{ order.refund_reason }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                    <button
                        v-if="order.payment_status === 'paid' && order.download_token_uses !== undefined"
                        type="button"
                        class="w-full rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent"
                        @click="resend"
                    >
                        Letöltő e-mail újraküldése
                    </button>

                    <template v-if="order.is_refundable">
                        <button
                            v-if="!refundOpen"
                            type="button"
                            class="mt-2 w-full rounded-lg border border-red-500/50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-red-500 hover:bg-red-500/10"
                            @click="refundOpen = true"
                        >
                            Visszatérítés
                        </button>
                        <form v-else class="mt-3 space-y-3" @submit.prevent="submitRefund">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Összeg (Ft) — üresen a teljes ({{ huf(refundable) }})</span>
                                <input v-model="refundForm.amount_cents" type="number" min="1" :max="refundable" :placeholder="refundable" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                <p v-if="refundForm.errors.amount_cents" class="mt-1 text-[11px] text-red-500">{{ refundForm.errors.amount_cents }}</p>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Indok (opcionális)</span>
                                <input v-model="refundForm.reason" type="text" maxlength="500" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" :disabled="refundForm.processing" class="rounded-lg bg-red-500 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-red-600 disabled:opacity-60">Visszatérítés indítása</button>
                                <button type="button" class="rounded-lg border border-border px-4 py-2 text-xs text-muted" @click="refundOpen = false">Mégse</button>
                            </div>
                        </form>
                    </template>
                    <p v-else-if="order.payment_status === 'refunded'" class="mt-2 text-[11px] text-muted">A rendelés teljes összege vissza lett térítve.</p>
                </div>
            </div>
        </div>

        <div v-if="invoicing.configured || invoicing.invoices.length" class="mt-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Számla</h2>
                <button
                    v-if="invoicing.configured && order.payment_status === 'paid' && !hasIssuedInvoice"
                    type="button"
                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent"
                    @click="issueInvoice"
                >
                    Számla kiállítása
                </button>
            </div>
            <div v-if="order.billing?.name" class="mt-2 text-[11px] text-muted">
                Vevő: {{ order.billing.name }}<template v-if="order.billing.tax_number"> · adószám: {{ order.billing.tax_number }}</template>
                <template v-if="order.billing.country"> · {{ order.billing.country }}</template>
                <template v-if="order.billing.zip || order.billing.city"> {{ order.billing.zip }} {{ order.billing.city }}</template>
                <template v-if="order.billing.address">, {{ order.billing.address }}</template>
            </div>
            <ul class="mt-3 space-y-2 text-xs">
                <li v-for="inv in invoicing.invoices" :key="inv.id" class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold" :class="inv.status === 'issued' ? 'text-content' : 'text-red-500'">
                        {{ inv.type === 'storno' ? 'Sztornó' : 'Számla' }}{{ inv.number ? ` · ${inv.number}` : '' }}
                    </span>
                    <span v-if="inv.status === 'failed'" class="text-red-500">— hiba: {{ inv.error }}</span>
                    <a v-if="inv.has_pdf" :href="`/admin/invoices/${inv.id}/pdf`" target="_blank" class="text-accent hover:underline">PDF</a>
                    <button
                        v-if="inv.type === 'normal' && inv.status === 'issued' && !invoicing.invoices.some((x) => x.type === 'storno' && x.status === 'issued')"
                        type="button"
                        class="text-red-500 hover:underline"
                        @click="stornoInvoice(inv.id)"
                    >
                        Sztornó
                    </button>
                </li>
                <li v-if="invoicing.invoices.length === 0" class="text-muted">Még nincs számla ehhez a rendeléshez.</li>
            </ul>
        </div>

        <div class="mt-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Eseménynapló</h2>
            <ol class="mt-3 space-y-3">
                <li v-if="order.timeline.length === 0" class="flex gap-3 text-xs">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-muted" />
                    <span><span class="text-content">Rendelés létrehozva</span> · <span class="text-muted">{{ fmt(order.created_at) }}</span></span>
                </li>
                <li v-for="e in order.timeline" :key="e.id" class="flex gap-3 text-xs">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent" />
                    <span>
                        <span class="text-content">{{ e.description }}</span>
                        <span class="text-muted"> · {{ fmt(e.created_at) }} · {{ e.causer }}</span>
                    </span>
                </li>
            </ol>
        </div>
    </AdminLayout>
</template>
