<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    enabled: Boolean,
    pending: Boolean,
    recoveryCodes: { type: Array, default: () => [] },
    qr: { type: String, default: null },
    setupKey: { type: String, default: null },
    isSuperadmin: Boolean,
    requiredForAdmins: Boolean,
});

const enableForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });
const codesForm = useForm({});
const policyForm = useForm({ required: props.requiredForAdmins });

const status = computed(() => (props.enabled ? 'on' : props.pending ? 'pending' : 'off'));

function copyCodes() {
    navigator.clipboard?.writeText(props.recoveryCodes.join('\n'));
}
</script>

<template>
    <Head title="Biztonság" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Fiók-biztonság</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Kétfaktoros hitelesítés (2FA) a fiókodhoz: bejelentkezéskor a jelszó mellé egy 6 jegyű,
            30 másodpercenként változó kódot kér egy authenticator appból (Google Authenticator, Authy, 1Password stb.).
        </p>

        <div class="mt-6 max-w-2xl space-y-6">
            <!-- Állapot -->
            <div class="rounded-[var(--radius-base)] border p-5" :class="status === 'on' ? 'border-emerald-500/40 bg-emerald-500/5' : 'border-border bg-surface-1'">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-content">
                        {{ status === 'on' ? 'Bekapcsolva' : status === 'pending' ? 'Beállítás alatt' : 'Kikapcsolva' }}
                    </span>
                    <span
                        class="h-2.5 w-2.5 rounded-full"
                        :class="status === 'on' ? 'bg-emerald-400' : status === 'pending' ? 'bg-amber-400' : 'bg-muted'"
                    />
                </div>

                <button
                    v-if="status === 'off'"
                    type="button"
                    :disabled="enableForm.processing"
                    class="mt-3 rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    @click="enableForm.post('/admin/settings/security/2fa', { preserveScroll: true })"
                >
                    Kétfaktoros hitelesítés bekapcsolása
                </button>
            </div>

            <!-- Beállítás: QR + megerősítés -->
            <div v-if="status === 'pending'" class="space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">1. Olvasd be az appoddal</h2>
                <div class="flex flex-wrap items-start gap-4">
                    <div class="rounded-lg bg-white p-2" v-html="qr" />
                    <div class="text-xs text-muted">
                        <p>Vagy add meg kézzel ezt a kulcsot:</p>
                        <code class="mt-1 block rounded bg-surface-2 px-2 py-1 font-mono text-[11px] text-content [overflow-wrap:anywhere]">{{ setupKey }}</code>
                    </div>
                </div>

                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">2. Írd be a megjelenő kódot</h2>
                <form class="flex flex-wrap items-start gap-2" @submit.prevent="confirmForm.post('/admin/settings/security/2fa/confirm', { preserveScroll: true, onSuccess: () => confirmForm.reset() })">
                    <input
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="123456"
                        class="w-32 rounded-lg border border-border bg-surface-2 px-3 py-2 text-center tracking-[0.3em] text-content focus:border-accent focus:outline-none"
                    />
                    <button type="submit" :disabled="confirmForm.processing" class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                        Megerősítés
                    </button>
                    <p v-if="confirmForm.errors.code" class="w-full text-xs text-accent">{{ confirmForm.errors.code }}</p>
                </form>
            </div>

            <!-- Helyreállító kódok -->
            <div v-if="recoveryCodes.length" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Helyreállító kódok</h2>
                    <button type="button" class="text-[11px] font-semibold uppercase tracking-wide text-accent hover:underline" @click="copyCodes">Másolás</button>
                </div>
                <p class="mt-1 text-xs text-muted">Tedd biztonságos helyre — ezekkel akkor is beléphetsz, ha nincs nálad a telefonod. Mindegyik egyszer használható.</p>
                <ul class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-xs text-content">
                    <li v-for="c in recoveryCodes" :key="c" class="rounded bg-surface-2 px-2 py-1">{{ c }}</li>
                </ul>
                <button
                    v-if="enabled"
                    type="button"
                    :disabled="codesForm.processing"
                    class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-content"
                    @click="codesForm.post('/admin/settings/security/2fa/recovery-codes', { preserveScroll: true })"
                >
                    Új kódok generálása
                </button>
            </div>

            <!-- Kikapcsolás -->
            <form
                v-if="enabled"
                class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5"
                @submit.prevent="disableForm.delete('/admin/settings/security/2fa', { preserveScroll: true, onSuccess: () => disableForm.reset() })"
            >
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Kikapcsolás</h2>
                <p class="mt-1 text-xs text-muted">A megerősítéshez add meg a jelszavad.</p>
                <div class="mt-2 flex flex-wrap items-start gap-2">
                    <input v-model="disableForm.password" type="password" autocomplete="current-password" placeholder="Jelszó" class="w-56 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    <button type="submit" :disabled="disableForm.processing" class="rounded-lg border border-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-accent hover:bg-accent hover:text-white disabled:opacity-60">
                        2FA kikapcsolása
                    </button>
                </div>
                <p v-if="disableForm.errors.password" class="mt-1 text-xs text-accent">{{ disableForm.errors.password }}</p>
            </form>

            <!-- Superadmin: kötelezővé tétel -->
            <div v-if="isSuperadmin" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Szabály minden adminra</h2>
                <label class="mt-2 flex items-start gap-2 text-sm text-content">
                    <input
                        v-model="policyForm.required"
                        type="checkbox"
                        class="mt-0.5 accent-[var(--color-accent)]"
                        @change="policyForm.put('/admin/settings/security/policy', { preserveScroll: true })"
                    />
                    <span>
                        Kötelező kétfaktoros hitelesítés minden admin / superadmin fióknál
                        <span class="mt-0.5 block text-xs text-muted">
                            Bekapcsolva a 2FA nélküli adminok a belépés után csak ezt az oldalt érik el, amíg be nem állítják.
                        </span>
                    </span>
                </label>
                <p v-if="policyForm.errors.required" class="mt-1 text-xs text-accent">{{ policyForm.errors.required }}</p>
            </div>
        </div>
    </AdminLayout>
</template>
