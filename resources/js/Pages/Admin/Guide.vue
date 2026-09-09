<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';

defineProps({
    // Ugyanaz a szerkezet, mint az oldalsáv (App\Support\AdminNavigation).
    sections: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Admin súgó" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Admin súgó</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">
            A csapat-felület minden menüpontja egy helyen, rövid magyarázattal. Ugyanez a bontás látszik bal oldalon az
            oldalsávban is — a szekció-fejlécekre kattintva ott össze lehet csukni a ritkán használt csoportokat.
        </p>

        <div class="mt-8 space-y-10">
            <section v-for="section in sections" :key="section.title">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-muted/70">{{ section.title }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in section.items"
                        :key="item.href"
                        :href="item.href"
                        class="group flex gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 transition-colors hover:border-accent/50 hover:bg-surface-2"
                    >
                        <span class="mt-0.5 shrink-0 text-muted group-hover:text-content">
                            <AdminNavIcon :name="item.icon" :size="20" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-content">{{ item.label }}</span>
                            <span class="mt-1 block text-xs leading-relaxed text-muted">{{ item.hint }}</span>
                        </span>
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
