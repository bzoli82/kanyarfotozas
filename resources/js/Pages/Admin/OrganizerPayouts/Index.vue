<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    organizers: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
});

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

const openId = ref(null);
const form = useForm({ organizer_id: '', event_id: '', amount_cents: null, reference: '', note: '', paid_at: '' });

function startPayout(organizer) {
    openId.value = organizer.id;
    form.reset();
    form.organizer_id = organizer.id;
}

function submit() {
    form.post('/admin/organizer-payouts', {
        preserveScroll: true,
        onSuccess: () => { openId.value = null; form.reset(); },
    });
}

function removePayout(id) {
    if (!confirm('Törlöd ezt a kifizetés-tételt?')) return;
    router.delete(`/admin/organizer-payouts/${id}`, { preserveScroll: true });
}

function organizerEvents(organizer) {
    return organizer.events ?? [];
}
</script>

<template>
    <Head title="Szervező kifizetések" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Szervező kifizetések</h1>
        <p class="mt-1 text-sm text-muted">
            Az esemény-szervezőknek járó bevétel-részesedés. A felhalmozott összeg számított (esemény bruttó × részesedés %),
            a kifizetést itt rögzíted egy sorral.
        </p>

        <div v-if="organizers.length === 0" class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6 text-sm text-muted">
            Még nincs „szervező" szerepkörű felhasználó. A Fotósok oldalon hívhatsz meg egyet (szerepkör: Szervező),
            majd az esemény szerkesztőjében rendeld hozzá az eseményhez a részesedés %-ával.
        </div>

        <div v-for="organizer in organizers" :key="organizer.id" class="mt-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-content">{{ organizer.name }}</div>
                    <div class="text-xs text-muted">{{ organizer.email }}</div>
                </div>
                <div class="flex flex-wrap gap-5 text-sm">
                    <div><span class="text-xs uppercase tracking-wide text-muted">Felhalmozott</span> <span class="ml-1 font-semibold text-content">{{ huf(organizer.accrued_cents) }}</span></div>
                    <div><span class="text-xs uppercase tracking-wide text-muted">Kifizetve</span> <span class="ml-1 font-semibold text-content">{{ huf(organizer.paid_cents) }}</span></div>
                    <div><span class="text-xs uppercase tracking-wide text-muted">Nyitva</span> <span class="ml-1 font-semibold" :class="organizer.outstanding_cents > 0 ? 'text-accent' : 'text-content'">{{ huf(organizer.outstanding_cents) }}</span></div>
                </div>
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent" @click="startPayout(organizer)">
                    Kifizetés rögzítése
                </button>
            </div>

            <div v-if="organizerEvents(organizer).length" class="mt-3 overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-2 text-[11px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Esemény</th>
                            <th class="px-3 py-2 font-semibold">Bruttó</th>
                            <th class="px-3 py-2 font-semibold">%</th>
                            <th class="px-3 py-2 font-semibold">Részesedés</th>
                            <th class="px-3 py-2 font-semibold">Kifizetve</th>
                            <th class="px-3 py-2 font-semibold">Nyitva</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="ev in organizerEvents(organizer)" :key="ev.id">
                            <td class="px-3 py-2 text-content">{{ ev.name }}</td>
                            <td class="px-3 py-2 text-muted">{{ huf(ev.gross_cents) }}</td>
                            <td class="px-3 py-2 text-muted">{{ ev.share_percent }}%</td>
                            <td class="px-3 py-2 text-content">{{ huf(ev.share_cents) }}</td>
                            <td class="px-3 py-2 text-muted">{{ huf(ev.paid_cents) }}</td>
                            <td class="px-3 py-2" :class="ev.outstanding_cents > 0 ? 'text-accent' : 'text-muted'">{{ huf(ev.outstanding_cents) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form v-if="openId === organizer.id" class="mt-4 grid gap-3 rounded-lg border border-accent/40 bg-surface-2 p-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Esemény (opcionális)</span>
                    <select v-model="form.event_id" class="w-full appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="">— Általános —</option>
                        <option v-for="ev in organizerEvents(organizer)" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Összeg (Ft)</span>
                    <input v-model.number="form.amount_cents" type="number" min="1" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="form.errors.amount_cents" class="mt-1 text-xs text-accent">{{ form.errors.amount_cents }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátum</span>
                    <input v-model="form.paid_at" type="date" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Hivatkozás</span>
                    <input v-model="form.reference" type="text" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Megjegyzés</span>
                    <input v-model="form.note" type="text" class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-3">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">Rögzítés</button>
                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="openId = null">Mégse</button>
                </div>
            </form>
        </div>

        <div v-if="recent.length" class="mt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Legutóbbi kifizetések</h2>
            <div class="mt-3 overflow-x-auto rounded-[var(--radius-base)] border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Dátum</th>
                            <th class="px-4 py-2.5 font-semibold">Szervező</th>
                            <th class="px-4 py-2.5 font-semibold">Esemény</th>
                            <th class="px-4 py-2.5 font-semibold">Összeg</th>
                            <th class="px-4 py-2.5 font-semibold">Hivatkozás</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="p in recent" :key="p.id">
                            <td class="px-4 py-2.5 text-muted">{{ p.paid_at }}</td>
                            <td class="px-4 py-2.5 text-content">{{ p.organizer }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ p.event ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-content">{{ huf(p.amount_cents) }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ p.reference ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="removePayout(p.id)">Törlés</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
