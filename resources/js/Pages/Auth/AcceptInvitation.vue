<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({
    token: String,
    name: String,
    email: String,
    isExpired: Boolean,
    agreementHtml: { type: String, default: '' },
});

const form = useForm({
    password: '',
    password_confirmation: '',
    agreement_accepted: false,
});

function submit() {
    form.post(`/invitations/${props.token}`);
}
</script>

<template>
    <Head title="Meghívó elfogadása" />

    <div class="grid min-h-screen place-items-center bg-surface-0 px-4 py-10">
        <div class="w-full max-w-lg">
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

                <div v-if="agreementHtml" class="mt-5">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Fotós Megállapodás</span>
                    <div class="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-border bg-surface-2 p-3 text-xs leading-relaxed text-muted [&_h2]:mt-2 [&_h2]:text-sm [&_h2]:font-bold [&_h2]:text-content [&_h3]:mt-2 [&_h3]:font-semibold [&_h3]:text-content [&_li]:ml-4 [&_li]:list-disc [&_p]:mt-1" v-html="agreementHtml"></div>
                    <label class="mt-2 flex gap-2 text-xs text-content">
                        <input v-model="form.agreement_accepted" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                        <span>Elolvastam és elfogadom a Fotós Megállapodást (benne az oldalon kívüli értékesítést tiltó záradékkal).</span>
                    </label>
                    <p v-if="form.errors.agreement_accepted" class="mt-1 text-xs text-accent">A megállapodás elfogadása kötelező.</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || (agreementHtml && !form.agreement_accepted)"
                    class="mt-6 w-full rounded-lg bg-accent py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Fiók aktiválása
                </button>
            </form>
        </div>
    </div>
</template>
