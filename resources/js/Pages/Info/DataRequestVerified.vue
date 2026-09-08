<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    type: { type: String, required: true },
    alreadyHandled: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="Kérelem megerősítve" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-accent/10 text-accent">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" /></svg>
                </div>
                <h1 class="font-display mt-5 text-2xl font-bold uppercase tracking-tight text-content">Kérelem megerősítve</h1>
                <p class="mt-2 text-sm text-muted">
                    <template v-if="alreadyHandled">Ezt a kérelmet már feldolgoztuk.</template>
                    <template v-else-if="type === 'delete'">
                        Rögzítettük az adattörlési kérelmedet. Munkatársunk 30 napon belül elvégzi a törlést, és e-mailben értesít.
                    </template>
                    <template v-else>
                        Rögzítettük az adatkiadási kérelmedet. Az adatkivonatot 30 napon belül elküldjük a megadott e-mail címre.
                    </template>
                </p>
                <Link href="/" class="mt-8 inline-block text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">Vissza a főoldalra</Link>
            </div>
        </section>
    </PublicLayout>
</template>
