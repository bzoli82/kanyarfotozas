<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

const useRecovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
    remember_device: false,
});

function submit() {
    form.transform((data) => (useRecovery.value
        ? { recovery_code: data.recovery_code, remember_device: data.remember_device }
        : { code: data.code, remember_device: data.remember_device }))
        .post('/two-factor-challenge', {
            onFinish: () => form.reset('code', 'recovery_code'),
        });
}
</script>

<template>
    <Head title="Kétfaktoros hitelesítés" />

    <div class="grid min-h-screen place-items-center bg-surface-0 px-4">
        <div class="w-full max-w-sm">
            <Link href="/" class="font-display block text-center text-lg font-bold tracking-tight text-content">
                <BrandLogo />
            </Link>
            <p class="mt-1 text-center text-xs text-muted">Kétfaktoros hitelesítés</p>

            <form class="mt-8 rounded-[var(--radius-base)] border border-border bg-surface-1 p-6" @submit.prevent="submit">
                <template v-if="!useRecovery">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">6 jegyű kód az appból</span>
                        <input
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            autofocus
                            maxlength="6"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-center text-lg tracking-[0.4em] text-content focus:border-accent focus:outline-none"
                        />
                    </label>
                </template>
                <template v-else>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Helyreállító kód</span>
                        <input
                            v-model="form.recovery_code"
                            type="text"
                            autocomplete="off"
                            autofocus
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        />
                    </label>
                </template>

                <p v-if="form.errors.code" class="mt-1 text-xs text-accent">{{ form.errors.code }}</p>

                <label class="mt-4 flex items-center gap-2 text-sm text-muted">
                    <input v-model="form.remember_device" type="checkbox" class="accent-[var(--color-accent)]" />
                    Megbízom ebben a gépben (30 nap)
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 w-full rounded-lg bg-accent py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                >
                    Belépés
                </button>

                <button
                    type="button"
                    class="mt-3 w-full text-center text-xs text-muted hover:text-content"
                    @click="useRecovery = !useRecovery"
                >
                    {{ useRecovery ? '← Vissza a kódhoz' : 'Elvesztettem a telefonom — helyreállító kód' }}
                </button>
            </form>

            <Link href="/login" class="mt-6 block text-center text-xs text-muted hover:text-content">← Mégsem</Link>
        </div>
    </div>
</template>
