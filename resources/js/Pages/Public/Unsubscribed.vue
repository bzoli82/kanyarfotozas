<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    location: { type: String, default: null },
});
</script>

<template>
    <Head title="Leiratkozás" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">Leiratkoztál</h1>
                <p class="mt-2 text-sm text-muted">
                    <template v-if="location">Nem küldünk több értesítőt a <span class="text-content">{{ location }}</span> helyszínről.</template>
                    <template v-else>A feliratkozásod törölve.</template>
                </p>
                <Link href="/events" class="mt-6 inline-block rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                    Vissza a galériákhoz
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
