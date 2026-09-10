<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    emails: { type: Object, required: true },
    filters: { type: Object, required: true },
    total: { type: Number, default: 0 },
});

const q = ref(props.filters.q ?? '');

function apply() {
    router.get('/admin/mail-log', { q: q.value || undefined }, { preserveState: true, replace: true });
}

function dt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU') : '—';
}

const mailableLabel = {
    OrderConfirmationMail: 'Vásárlási visszaigazoló',
    DownloadReminderMail: 'Letöltési emlékeztető',
    OrderRefundedMail: 'Visszatérítés',
    ContactConfirmationMail: 'Kapcsolat — visszaigazolás',
    ContactNotificationMail: 'Kapcsolat — értesítés',
    ContactReplyMail: 'Kapcsolat — válasz',
    PhotographerInvitationMail: 'Fotós meghívó',
    TemporaryPasswordMail: 'Ideiglenes jelszó',
    PurchaseOtpMail: 'Vásárlás — OTP',
    AbandonedCartMail: 'Elhagyott kosár',
    EventLiveNotificationMail: 'Esemény elérhető',
    DataRequestVerifyMail: 'GDPR — megerősítés',
    PhotographerReportMail: 'Fotós riport',
};
</script>

<template>
    <Head title="E-mail napló" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">E-mail napló</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A rendszer által <strong class="text-content">sikeresen elküldött</strong> e-mailek (címzett + tárgy).
            „A vevő nem kapta meg a letöltő linket" típusú kérdésekhez. Az elmúlt ~30 napot tartja meg.
            Ha egy levél <strong class="text-content">nem</strong> jelenik meg itt, meg sem próbált kimenni — nézd meg a Hibanaplót és a Kritikus beállítások → E-mail állapotot.
        </p>

        <div class="mt-5 flex items-end gap-3">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Keresés (címzett / tárgy)</span>
                <input v-model="q" type="text" class="w-64 rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" @keyup.enter="apply" />
            </label>
            <button type="button" class="rounded-lg bg-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="apply">Szűrés</button>
            <span class="ml-auto text-[11px] text-muted">Összesen {{ total }} bejegyzés</span>
        </div>

        <div class="mt-4 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full min-w-[640px] text-left text-xs">
                <thead class="bg-surface-2 text-[10px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Időpont</th>
                        <th class="px-4 py-2.5 font-semibold">Címzett</th>
                        <th class="px-4 py-2.5 font-semibold">Típus</th>
                        <th class="px-4 py-2.5 font-semibold">Tárgy</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in emails.data" :key="m.id" class="border-t border-border">
                        <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ dt(m.created_at) }}</td>
                        <td class="px-4 py-2.5 text-content">{{ m.recipient }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ m.mailable ? (mailableLabel[m.mailable] ?? m.mailable) : '—' }}</td>
                        <td class="px-4 py-2.5 text-content">{{ m.subject }}</td>
                    </tr>
                    <tr v-if="emails.data.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-muted">Nincs a szűrésnek megfelelő bejegyzés.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="emails.last_page > 1" class="mt-6 flex flex-wrap justify-center gap-1">
            <Link
                v-for="(link, i) in emails.links"
                :key="i"
                :href="link.url ?? ''"
                v-html="link.label"
                class="rounded-[var(--radius-base)] border px-3 py-1.5 text-xs"
                :class="[
                    link.active ? 'border-accent text-accent' : 'border-border text-muted hover:text-content',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
            />
        </div>
    </AdminLayout>
</template>
