<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    social: { type: Object, required: true },
});

const fields = [
    { key: 'facebook', label: 'Facebook', placeholder: 'https://facebook.com/oldaladneve' },
    { key: 'instagram', label: 'Instagram', placeholder: 'https://instagram.com/felhasznalonev' },
    { key: 'youtube', label: 'YouTube', placeholder: 'https://youtube.com/@csatorna' },
    { key: 'tiktok', label: 'TikTok', placeholder: 'https://tiktok.com/@felhasznalonev' },
];

const form = useForm({
    facebook: props.social.facebook ?? '',
    instagram: props.social.instagram ?? '',
    youtube: props.social.youtube ?? '',
    tiktok: props.social.tiktok ?? '',
});

function save() {
    form.put('/admin/settings/social', { preserveScroll: true });
}
</script>

<template>
    <Head title="Közösségi média" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Közösségi média</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A kitöltött linkek megjelennek a publikus <strong class="text-content">„Közösség" oldalon</strong> (fejléc-menü) és a
            <strong class="text-content">láblécben</strong>. Az üresen hagyott platform sehol nem jelenik meg.
            Elég a profil URL-je — a <code>https://</code> automatikusan bekerül.
        </p>

        <form class="mt-6 max-w-xl space-y-4" @submit.prevent="save">
            <label v-for="f in fields" :key="f.key" class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ f.label }}</span>
                <input
                    v-model="form[f.key]"
                    type="text"
                    :placeholder="f.placeholder"
                    class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none"
                />
                <p v-if="form.errors[f.key]" class="mt-1 text-xs text-red-500">{{ form.errors[f.key] }}</p>
            </label>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Mentés
            </button>
        </form>
    </AdminLayout>
</template>
