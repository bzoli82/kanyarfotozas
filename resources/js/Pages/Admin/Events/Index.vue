<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    events: Object,
    filters: Object,
    countries: { type: Array, default: () => [] },
    photographers: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search ?? '');
const countryId = ref(props.filters?.country_id ?? '');
const photographerId = ref(props.filters?.photographer_id ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');

function submitSearch() {
    router.get(
        '/admin/events',
        {
            search: search.value || undefined,
            country_id: countryId.value || undefined,
            photographer_id: photographerId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    search.value = '';
    countryId.value = '';
    photographerId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    submitSearch();
}

const statusLabel = {
    draft: 'Vázlat',
    announced: 'Meghirdetve',
    live: 'Élő',
    archived: 'Archivált',
};
</script>

<template>
    <Head title="Események" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Események</h1>
            <Link href="/admin/events/create" class="rounded-lg bg-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                + Új esemény
            </Link>
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="submitSearch">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Keresés</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Név, helyszín vagy ország…"
                    class="w-56 rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none"
                />
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Ország</span>
                <select v-model="countryId" class="appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes</option>
                    <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.flag_emoji }} {{ c.name_hu }}</option>
                </select>
            </label>

            <label v-if="photographers.length" class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós</span>
                <select v-model="photographerId" class="appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes</option>
                    <option v-for="p in photographers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátumtól</span>
                <input v-model="dateFrom" type="date" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Dátumig</span>
                <input v-model="dateTo" type="date" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>

            <button type="submit" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent">
                Szűrés
            </button>
            <button type="button" class="px-2 py-2 text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="resetFilters">
                Törlés
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Esemény</th>
                        <th class="px-4 py-3 font-semibold">Ország</th>
                        <th class="px-4 py-3 font-semibold">Dátum</th>
                        <th class="px-4 py-3 font-semibold">Média</th>
                        <th class="px-4 py-3 font-semibold">Státusz</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="event in events.data" :key="event.id" class="hover:bg-surface-1">
                        <td class="px-4 py-3">
                            <Link :href="`/admin/events/${event.id}`" class="font-medium text-content hover:text-accent">
                                {{ event.name }}
                            </Link>
                            <div class="text-xs text-muted">{{ event.location }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            <span v-if="event.country">{{ event.country.flag_emoji }} {{ event.country.name_hu }}</span>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ event.event_date?.slice(0, 10) }}</td>
                        <td class="px-4 py-3 text-muted">{{ event.media_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                :class="event.status === 'live' ? 'border-accent text-accent' : 'border-border text-muted'"
                            >
                                {{ statusLabel[event.status] ?? event.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/admin/events/${event.id}`" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">
                                {{ event.can_edit ? 'Szerkesztés' : 'Megnyitás' }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="events.data.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">Nincs megjeleníthető esemény.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="events.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="(link, i) in events.links"
                :key="i"
                :href="link.url ?? ''"
                v-html="link.label"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="[
                    link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
                preserve-scroll
            />
        </div>
    </AdminLayout>
</template>
