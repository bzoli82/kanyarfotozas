<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    threads: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
});

function openThread(id) {
    router.get('/photographer/messages', { thread: id }, { preserveState: true, preserveScroll: true });
}

const replyForm = useForm({ body: '' });

function submitReply() {
    replyForm.post(`/photographer/messages/${props.selected.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

function dt(value) {
    return value ? new Date(value).toLocaleString('hu-HU') : '';
}
</script>

<template>
    <Head title="Üzenetek" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Üzenetek</h1>
        <p class="mt-1 text-sm text-muted">
            A látogatók által a fotóidhoz küldött kérdések. A válaszod e-mailben megy a kérdezőnek.
            Ha egy kérdésre nem válaszolsz, az adminok a nevedben megtehetik.
        </p>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,22rem)_1fr]">
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
                        <span v-if="!t.answered" class="shrink-0 rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-semibold text-white">Új</span>
                    </div>
                    <div class="mt-1 truncate text-xs text-muted">{{ t.name }}</div>
                    <div class="mt-1 text-[11px] text-muted">{{ dt(t.last_activity_at) }}</div>
                </button>
                <p v-if="threads.length === 0" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 text-sm text-muted">
                    Még nincs beérkezett üzeneted.
                </p>
            </div>

            <div v-if="selected" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="border-b border-border pb-4">
                    <h2 class="text-base font-semibold text-content">{{ selected.subject }}</h2>
                    <p class="mt-0.5 text-sm text-muted">
                        {{ selected.name }} · <a :href="`mailto:${selected.email}`" class="hover:text-accent">{{ selected.email }}</a>
                        <template v-if="selected.event"> · {{ selected.event.name }}</template>
                    </p>
                </div>

                <div class="space-y-4 py-4">
                    <div class="rounded-lg bg-surface-2 p-3">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted">{{ selected.name }} · {{ dt(selected.created_at) }}</div>
                        <p class="whitespace-pre-wrap text-sm text-content">{{ selected.message }}</p>
                    </div>

                    <div v-for="r in selected.replies" :key="r.id" class="rounded-lg border border-border p-3">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted">
                            <template v-if="r.on_behalf_of">{{ r.on_behalf_of }} nevében (admin: {{ r.author }})</template>
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
                        ></textarea>
                    </label>
                    <p v-if="replyForm.errors.body" class="mt-1 text-xs text-accent">{{ replyForm.errors.body }}</p>
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
