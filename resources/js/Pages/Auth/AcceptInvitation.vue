<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({
    token: String,
    name: String,
    email: String,
    isExpired: Boolean,
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/invitations/${props.token}`);
}
</script>

<template>
    <Head title="Meghívó elfogadása" />

    <div class="grid min-h-screen place-items-center bg-surface-0 px-4">
        <div class="w-full max-w-sm">
            <Link href="/" class="font-display block text-center text-lg font-bold tracking-tight text-content">
                <BrandLogo />
            </Link>
            <p class="mt-1 text-center text-xs text-muted">Meghívó elfogadása</p>

            <div v-if="isExpired" class="mt-8 rounded-[var(--radius-base)] border border-accent bg-accent/10 p-6 text-center text-sm text-content">
                Ez a meghívó már lejárt. Kérj újat az adminisztrátortól.
            </div>

            <form v-else class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6" @submit.prevent="submit">
                <p class="text-sm text-content">
                    Szia <strong>{{ name }}</strong>! Add meg a jelszavad, hogy aktiváld a fiókodat ehhez az e-mail címhez:
                    <span class="text-muted">{{ email }}</span>.
                </p>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Jelszó</span>
                    <input
                        v-model="form.password"
                        type="password"
                        autofocus
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-accent">{{ form.errors.password }}</p>
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Jelszó megerősítése</span>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 w-full rounded-lg bg-accent py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                >
                    Fiók aktiválása
                </button>
            </form>
        </div>
    </div>
</template>
