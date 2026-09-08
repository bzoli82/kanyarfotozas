<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    threads: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    filter: { type: String, default: '' },
    counts: { type: Object, default: () => ({ all: 0, unanswered: 0, photographer: 0 }) },
});

const filters = [
    { key: '', label: 'Összes' },
    { key: 'unanswered', label: 'Megválaszolatlan' },
    { key: 'photographer', label: 'Fotósnak címzett' },
    { key: 'support', label: 'Support' },
    { key: 'resolved', label: 'Lezárt' },
];

function setFilter(key) {
    router.get('/admin/messages', { filter: key, thread: props.selected?.id }, { preserveState: true, preserveScroll: true });
}

function openThread(id) {
    router.get('/admin/messages', { filter: props.filter, thread: id }, { preserveState: true, preserveScroll: true });
}

const onBehalf = ref(false);
const replyForm = useForm({ body: '', on_behalf_of_id: null });

function submitReply() {
    replyForm
        .transform((data) => ({
            body: data.body,
            on_behalf_of_id: onBehalf.value ? props.selected?.photographer?.id : null,
        }))
        .post(`/admin/messages/${props.selected.id}/reply`, {
            preserveScroll: true,
            onSuccess: () => { replyForm.reset(); onBehalf.value = false; },
        });
}

function setStatus(status) {
    router.put(`/admin/messages/${props.selected.id}/status`, { status }, { preserveScroll: true });
}

const statusLabel = { new: 'Új', in_progress: 'Folyamatban', resolved: 'Lezárt' };

function dt(value) {
    return value ? new Date(value).toLocaleString('hu-HU') : '';
}
</script>

<template>
    <Head title="Üzenetek" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Üzenetek</h1>
        <p class="mt-1 text-sm text-muted">
            Minden kapcsolati üzenetváltás — a support és a feltöltő fotósoknak címzett kérdések is.
            A fotósnak címzett szálaknál a fotós nevében is válaszolhatsz, ha ő épp nem elérhető.
        </p>

        <div class="mt-4 flex flex-wrap gap-1 border-b border-border">
            <button
                v-for="f in filters"
                :key="f.key"
                type="button"
                class="border-b-2 px-3 py-2 text-xs font-semibold uppercase tracking-wide"
                :class="filter === f.key ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-content'"
                @click="setFilter(f.key)"
            >
                {{ f.label }}
                <span v-if="f.key === 'unanswered' && counts.unanswered" class="ml-1 rounded-full bg-accent px-1.5 text-[10px] text-white">{{ counts.unanswered }}</span>
            </button>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,22rem)_1fr]">
            <!-- Szállista -->
            <div class="space-y-1.5">
                <button
                    v-for="t in threads"
                    :key="t.id"
                    type="button"
                    class="block w-full rounded-[var(--radius-base)] border p-3 text-left transition-colors"
                    :class="selected && selected.id === t.id ? 'border-accent bg-surface-1' : 'border-border bg-surface-1 hover:border-accent/50'"
                    @click="openThread(t.id)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-medium text-content">{{ t.subject }}</span>
                        <span v-if="!t.answered && t.status !== 'resolved'" class="shrink-0 rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-semibold text-white">Új</span>
                    </div>
                    <div class="mt-1 truncate text-xs text-muted">{{ t.name }} · {{ t.email }}</div>
                    <div class="mt-1 flex items-center gap-2 text-[11px] text-muted">
                        <span v-if="t.photographer" class="rounded bg-surface-2 px-1.5 py-0.5">→ {{ t.photographer.name }}</span>
                        <span>{{ dt(t.last_activity_at) }}</span>
                        <span v-if="t.replies_count">· {{ t.replies_count }} válasz</span>
                    </div>
                </button>
                <p v-if="threads.length === 0" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 text-sm text-muted">
                    Nincs a szűrésnek megfelelő üzenet.
                </p>
            </div>

            <!-- Kiválasztott szál -->
            <div v-if="selected" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
                    <div>
                        <h2 class="text-base font-semibold text-content">{{ selected.subject }}</h2>
                        <p class="mt-0.5 text-sm text-muted">
                            {{ selected.name }} · <a :href="`mailto:${selected.email}`" class="hover:text-accent">{{ selected.email }}</a>
                        </p>
                        <p v-if="selected.photographer" class="mt-0.5 text-xs text-muted">
                            Címzett fotós: <span class="text-content">{{ selected.photographer.name }}</span>
                            <template v-if="selected.event"> · {{ selected.event.name }}</template>
                        </p>
                    </div>
                    <select
                        :value="selected.status"
                        class="appearance-none rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs text-content focus:border-accent focus:outline-none"
                        @change="setStatus($event.target.value)"
                    >
                        <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <div class="space-y-4 py-4">
                    <div class="rounded-lg bg-surface-2 p-3">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted">{{ selected.name }} · {{ dt(selected.created_at) }}</div>
                        <p class="whitespace-pre-wrap text-sm text-content">{{ selected.message }}</p>
                    </div>

                    <div v-for="r in selected.replies" :key="r.id" class="rounded-lg border border-border p-3">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted">
                            <template v-if="r.on_behalf_of">{{ r.on_behalf_of }} nevében (írta: {{ r.author }})</template>
                            <template v-else>{{ r.author ?? 'Csapat' }}</template>
                            · {{ dt(r.created_at) }}
                        </div>
                        <p class="whitespace-pre-wrap text-sm text-content">{{ r.body }}</p>
                    </div>
                </div>

                <form class="border-t border-border pt-4" @submit.prevent="submitReply">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Válasz</span>
                        <textarea
                            v-model="replyForm.body"
                            rows="4"
                            required
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"
                            placeholder="A válasz e-mailben megy a feladónak…"
                        ></textarea>
                    </label>
                    <p v-if="replyForm.errors.body" class="mt-1 text-xs text-accent">{{ replyForm.errors.body }}</p>

                    <label v-if="selected.photographer" class="mt-2 flex items-center gap-2 text-sm text-content">
                        <input v-model="onBehalf" type="checkbox" class="accent-[var(--color-accent)]" />
                        Válasz <strong>{{ selected.photographer.name }}</strong> fotós nevében (ha ő épp nem elérhető)
                    </label>

                    <button
                        type="submit"
                        :disabled="replyForm.processing"
                        class="mt-3 rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    >
                        Válasz küldése
                    </button>
                </form>
            </div>
            <div v-else class="hidden rounded-[var(--radius-base)] border border-border bg-surface-1 p-8 text-center text-sm text-muted lg:block">
                Válassz egy üzenetet a listából.
            </div>
        </div>
    </AdminLayout>
</template>
