<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    enabled: props.settings.enabled,
    message: props.settings.message,
});

function save() {
    form.put('/admin/settings/maintenance', { preserveScroll: true });
}
</script>

<template>
    <Head title="Karbantartási mód" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Karbantartási mód</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Bekapcsolva a látogatók egy „hamarosan" lapot látnak (HTTP 503). Az
            <strong class="text-content">admin felület</strong>, a <strong class="text-content">bejelentkezés</strong>,
            a meghívó-elfogadás és a <strong class="text-content">fizetési webhookok</strong> (folyamatban lévő rendelések)
            elérhetők maradnak. A bejelentkezett csapattagok is látják az oldalt.
        </p>

        <div
            v-if="settings.enabled"
            class="mt-4 max-w-xl rounded-[var(--radius-base)] border-2 border-amber-500/60 bg-amber-500/10 px-4 py-3 text-sm text-content"
        >
            ⚠️ A karbantartási mód jelenleg <strong>BE van kapcsolva</strong> — a látogatók nem érik el az oldalt.
        </div>

        <form class="mt-6 max-w-xl space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
            <label class="flex items-start gap-2.5 text-sm text-content">
                <input v-model="form.enabled" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                <span>
                    <strong>Karbantartási mód bekapcsolása</strong>
                    <span class="mt-0.5 block text-[11px] text-muted">A publikus oldal (főoldal, galéria, kosár, kapcsolat…) a „hamarosan" lapot mutatja.</span>
                </span>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Üzenet a látogatóknak (opcionális)</span>
                <textarea
                    v-model="form.message"
                    rows="3"
                    maxlength="500"
                    :placeholder="settings.default_message"
                    class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                ></textarea>
                <p class="mt-1 text-[11px] text-muted">Üresen hagyva az alapértelmezett szöveg jelenik meg.</p>
            </label>

            <div class="pt-1">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white disabled:opacity-60"
                    :class="form.enabled ? 'bg-amber-600 hover:bg-amber-700' : 'bg-accent hover:bg-accent-hover'"
                >
                    {{ form.enabled ? 'Mentés — karbantartás BE' : 'Mentés' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
