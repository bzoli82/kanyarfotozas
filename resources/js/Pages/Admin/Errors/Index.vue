<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, required: true },
    openCount: { type: Number, default: 0 },
});

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'open');

function apply() {
    router.get('/admin/errors', { q: q.value, status: status.value }, { preserveState: true, replace: true });
}

function dt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU') : '—';
}

function resolve(id) {
    router.post(`/admin/errors/${id}/resolve`, {}, { preserveScroll: true });
}
function reopen(id) {
    router.post(`/admin/errors/${id}/reopen`, {}, { preserveScroll: true });
}
function remove(id) {
    if (!confirm('Törlöd ezt a hibabejegyzést?')) return;
    router.delete(`/admin/errors/${id}`, { preserveScroll: true });
}
function resolveAll() {
    if (!confirm('Minden nyitott hibát lezársz?')) return;
    router.post('/admin/errors/resolve-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Hibanapló" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">
                Hibanapló
                <span v-if="openCount" class="ml-2 rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-semibold text-red-500">{{ openCount }} nyitott</span>
            </h1>
            <button v-if="openCount" type="button" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="resolveAll">
                Összes lezárása
            </button>
        </div>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A kezeletlen kivételek ujjlenyomat szerint csoportosítva. Ugyanaz a hiba egy sorban gyűlik (előfordulás-számlálóval).
            Új hibánál a superadminok e-mailt kapnak (a Kritikus beállításoknál kapcsolható).
        </p>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Keresés</span>
                <input v-model="q" type="text" placeholder="Kivétel, üzenet, URL…" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Állapot</span>
                <select v-model="status" class="appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="open">Nyitott</option>
                    <option value="resolved">Lezárt</option>
                    <option value="all">Összes</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg border border-border px-5 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent">Szűrés</button>
        </form>

        <div class="mt-4 space-y-3">
            <div v-for="e in events.data" :key="e.id" class="rounded-[var(--radius-base)] border p-4" :class="e.resolved_at ? 'border-border bg-surface-1 opacity-70' : 'border-red-500/30 bg-surface-1'">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <span class="font-mono text-sm font-semibold text-content [overflow-wrap:anywhere]">{{ e.exception_class }}</span>
                        <span class="ml-2 rounded-full border border-border px-2 py-0.5 text-[10px] text-muted">{{ e.count }}×</span>
                        <span v-if="e.resolved_at" class="ml-2 rounded-full border border-emerald-500/40 px-2 py-0.5 text-[10px] text-emerald-400">lezárva</span>
                    </div>
                    <div class="flex shrink-0 gap-3 text-xs font-semibold uppercase tracking-wide">
                        <button v-if="!e.resolved_at" type="button" class="text-accent hover:text-accent-hover" @click="resolve(e.id)">Lezár</button>
                        <button v-else type="button" class="text-muted hover:text-content" @click="reopen(e.id)">Újranyit</button>
                        <button type="button" class="text-muted hover:text-content" @click="remove(e.id)">Töröl</button>
                    </div>
                </div>
                <p class="mt-1 text-xs text-muted [overflow-wrap:anywhere]">{{ e.message }}</p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted">
                    <span v-if="e.location" class="font-mono [overflow-wrap:anywhere]">{{ e.location }}</span>
                    <span v-if="e.url">{{ e.method }} {{ e.url }}</span>
                    <span>először: {{ dt(e.first_seen_at) }}</span>
                    <span>legutóbb: {{ dt(e.last_seen_at) }}</span>
                </div>
            </div>
            <p v-if="events.data.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border p-8 text-center text-sm text-muted">
                Nincs a szűrésnek megfelelő hiba. 🎉
            </p>
        </div>

        <div v-if="events.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
            <Link
                v-for="(link, i) in events.links"
                :key="i"
                :href="link.url ?? ''"
                v-html="link.label"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="[link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content', !link.url && 'pointer-events-none opacity-40']"
            />
        </div>
    </AdminLayout>
</template>
