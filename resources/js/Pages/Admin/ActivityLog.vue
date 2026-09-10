<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    activity: { type: Object, required: true },
    filters: { type: Object, required: true },
    subjectTypes: { type: Array, default: () => [] },
    causers: { type: Array, default: () => [] },
});

const q = ref(props.filters.q ?? '');
const subject = ref(props.filters.subject ?? '');
const causer = ref(props.filters.causer ?? '');

function apply() {
    router.get('/admin/activity', { q: q.value || undefined, subject: subject.value || undefined, causer: causer.value || undefined }, { preserveState: true, replace: true });
}

function reset() {
    q.value = '';
    subject.value = '';
    causer.value = '';
    apply();
}

function dt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU') : '—';
}

const eventLabel = {
    created: 'létrehozás',
    updated: 'módosítás',
    deleted: 'törlés',
    restored: 'visszaállítás',
};

const subjectLabel = {
    Order: 'Rendelés',
    Event: 'Esemény',
    User: 'Felhasználó',
    Invoice: 'Számla',
};

const hasFilter = computed(() => q.value || subject.value || causer.value);
</script>

<template>
    <Head title="Tevékenység-napló" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Tevékenység-napló</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Ki mit módosított az oldalon — rendelés, esemény, beállítás, fizetés-lezárás, visszatérítés.
            Csak megtekintés. Az <strong class="text-content">elmúlt ~1 hónapot</strong> mutatja (a régebbi
            bejegyzéseket a rendszer automatikusan törli); a média-feltöltések nem kerülnek be.
        </p>

        <div class="mt-5 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Keresés a leírásban</span>
                <input v-model="q" type="text" placeholder="pl. visszatérítés, fizetés" class="w-56 rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" @keyup.enter="apply" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Tárgy típusa</span>
                <select v-model="subject" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" @change="apply">
                    <option value="">Mind</option>
                    <option v-for="t in subjectTypes" :key="t" :value="t">{{ subjectLabel[t] ?? t }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Ki csinálta</span>
                <select v-model="causer" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" @change="apply">
                    <option value="">Bárki</option>
                    <option v-for="c in causers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>
            <button type="button" class="rounded-lg bg-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="apply">Szűrés</button>
            <button v-if="hasFilter" type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="reset">Törlés</button>
        </div>

        <div class="mt-4 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="bg-surface-2 text-[10px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Időpont</th>
                        <th class="px-4 py-2.5 font-semibold">Ki</th>
                        <th class="px-4 py-2.5 font-semibold">Művelet</th>
                        <th class="px-4 py-2.5 font-semibold">Leírás</th>
                        <th class="px-4 py-2.5 font-semibold">Tárgy</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in activity.data" :key="a.id" class="border-t border-border">
                        <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ dt(a.created_at) }}</td>
                        <td class="px-4 py-2.5">
                            <span v-if="a.causer" class="text-content">{{ a.causer.name }}</span>
                            <span v-else class="text-muted">rendszer</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ eventLabel[a.event] ?? a.event ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-content">
                            {{ ['created', 'updated', 'deleted', 'restored'].includes(a.description) ? '—' : a.description }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5">
                            <template v-if="a.subject_type">
                                <Link v-if="a.subject_link" :href="a.subject_link" class="text-accent hover:underline">
                                    {{ subjectLabel[a.subject_type] ?? a.subject_type }} #{{ a.subject_id }}
                                </Link>
                                <span v-else class="text-muted">{{ subjectLabel[a.subject_type] ?? a.subject_type }} #{{ a.subject_id }}</span>
                            </template>
                            <span v-else class="text-muted">—</span>
                        </td>
                    </tr>
                    <tr v-if="activity.data.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-muted">Nincs a szűrésnek megfelelő bejegyzés.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="activity.last_page > 1" class="mt-6 flex flex-wrap justify-center gap-1">
            <Link
                v-for="(link, i) in activity.links"
                :key="i"
                :href="link.url ?? ''"
                v-html="link.label"
                class="rounded-[var(--radius-base)] border px-3 py-1.5 text-xs"
                :class="[
                    link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
            />
        </div>
    </AdminLayout>
</template>
