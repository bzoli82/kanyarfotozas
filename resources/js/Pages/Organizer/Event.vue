<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { mediaUrl } = useMediaUrl();

defineProps({
    event: Object,
    revenue: Object,
    topMedia: { type: Array, default: () => [] },
});

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}
</script>

<template>
    <Head :title="event.name" />

    <AdminLayout>
        <Link href="/organizer/dashboard" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">← Áttekintő</Link>
        <h1 class="font-display mt-1 text-xl font-bold uppercase tracking-tight text-content">{{ event.name }}</h1>
        <p class="text-sm text-muted">{{ event.country }} · {{ event.location }} · {{ event.event_date }}</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(revenue.gross_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Bruttó eladás</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ revenue.orders }} / {{ revenue.buyers }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Rendelés / vásárló</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ revenue.media_count }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Feltöltött média · {{ revenue.views }} megtekintés</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="text-xl font-bold text-content">{{ huf(revenue.share_cents) }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Részesedésed ({{ revenue.share_percent }}%)</div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-6 rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3 text-sm">
            <div><span class="text-xs uppercase tracking-wide text-muted">Kifizetve</span> <span class="ml-1 font-semibold text-content">{{ huf(revenue.paid_cents) }}</span></div>
            <div><span class="text-xs uppercase tracking-wide text-muted">Nyitott egyenleg</span> <span class="ml-1 font-semibold" :class="revenue.outstanding_cents > 0 ? 'text-accent' : 'text-content'">{{ huf(revenue.outstanding_cents) }}</span></div>
        </div>

        <template v-if="topMedia.length">
            <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-content">Legnépszerűbb felvételek</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="m in topMedia" :key="m.id" class="overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-surface-2 text-xs text-muted">
                        <img v-if="m.thumbnail_s3_key" :src="mediaUrl(m.thumbnail_s3_key)" alt="" class="h-full w-full object-cover" />
                        <span v-else>{{ m.type === 'video' ? 'Videó' : 'Kép' }}</span>
                    </div>
                    <div class="p-2 text-center text-xs text-muted">{{ m.sales_count }} eladás</div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>
