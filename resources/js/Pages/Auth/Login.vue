<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Bejelentkezés" />

    <div class="grid min-h-screen place-items-center bg-surface-0 px-4">
        <div class="w-full max-w-sm">
            <Link href="/" class="font-display block text-center text-lg font-bold tracking-tight text-content">
                <BrandLogo />
            </Link>
            <p class="mt-1 text-center text-xs text-muted">Admin / fotós belépés</p>

            <form class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">E-mail</span>
                    <input
                        v-model="form.email"
                        type="email"
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-accent">{{ form.errors.email }}</p>
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Jelszó</span>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-accent">{{ form.errors.password }}</p>
                </label>

                <label class="mt-4 flex items-center gap-2 text-sm text-muted">
                    <input v-model="form.remember" type="checkbox" class="accent-[var(--color-accent)]" />
                    Emlékezz rám
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 w-full rounded-lg bg-accent py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                >
                    Bejelentkezés
                </button>
            </form>

            <Link href="/" class="mt-6 block text-center text-xs text-muted hover:text-content">← Vissza a főoldalra</Link>
        </div>
    </div>
</template>
