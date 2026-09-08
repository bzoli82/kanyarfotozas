<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ requests: { type: Array, default: () => [] } });

const openId = ref(null);
const noteForm = useForm({ note: '' });

function complete(id) {
    noteForm.post(`/admin/data-requests/${id}/complete`, { preserveScroll: true, onSuccess: () => { openId.value = null; noteForm.reset(); } });
}
function reject(id) {
    noteForm.post(`/admin/data-requests/${id}/reject`, { preserveScroll: true, onSuccess: () => { openId.value = null; noteForm.reset(); } });
}

const typeLabel = { export: 'Adatkiadás', delete: 'Törlés' };
const statusLabel = { pending: 'Megerősítésre vár', verified: 'Feldolgozásra vár', completed: 'Kész', rejected: 'Elutasítva' };
const statusCls = { pending: 'text-muted', verified: 'text-amber-400', completed: 'text-emerald-400', rejected: 'text-red-500' };

function fmt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="GDPR kérelmek" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">GDPR adatkérelmek</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A megerősített (feldolgozásra váró) kérelmeknél töltsd le az adatkivonatot, vagy végezd el a törlést.
            A törlés a rendelések e-mail címét anonimizálja (a számviteli adat megmarad), és törli a kapcsolati
            üzeneteket / esemény-feliratkozásokat.
        </p>

        <div class="mt-5 space-y-3">
            <div v-for="r in requests" :key="r.id" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="font-semibold text-content">{{ r.email }}</span>
                        <span class="ml-2 text-xs text-muted">{{ typeLabel[r.type] }} · {{ fmt(r.created_at) }}</span>
                    </div>
                    <span class="text-xs font-semibold uppercase" :class="statusCls[r.status]">{{ statusLabel[r.status] }}</span>
                </div>
                <p v-if="r.note" class="mt-2 text-[11px] text-muted">{{ r.note }}</p>

                <div v-if="r.status === 'verified'" class="mt-3 flex flex-wrap items-center gap-2">
                    <a
                        v-if="r.type === 'export'"
                        :href="`/admin/data-requests/${r.id}/download`"
                        class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent"
                    >Adatkivonat letöltése</a>

                    <template v-if="openId === r.id">
                        <input v-model="noteForm.note" type="text" placeholder="Megjegyzés (opcionális)" maxlength="240" class="rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs text-content focus:border-accent focus:outline-none" />
                        <button type="button" :disabled="noteForm.processing" class="rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="complete(r.id)">
                            {{ r.type === 'delete' ? 'Törlés végrehajtása' : 'Késznek jelöl' }}
                        </button>
                        <button type="button" class="rounded-lg border border-red-500/50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-red-500 hover:bg-red-500/10" @click="reject(r.id)">Elutasít</button>
                        <button type="button" class="text-xs text-muted" @click="openId = null">Mégse</button>
                    </template>
                    <button v-else type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent" @click="openId = r.id">Feldolgozás</button>
                </div>
            </div>

            <p v-if="requests.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border bg-surface-1 p-10 text-center text-sm text-muted">
                Nincs adatkérelem.
            </p>
        </div>
    </AdminLayout>
</template>
