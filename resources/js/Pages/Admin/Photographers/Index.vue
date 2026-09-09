<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users: Array,
    pendingInvitations: Array,
    filters: Object,
    payoutTotals: { type: Object, default: () => ({ outstanding_cents: 0, paid_cents: 0 }) },
    contactsPublic: { type: Boolean, default: false },
});

const contactsForm = useForm({ contacts_public: props.contactsPublic });

function toggleContacts() {
    contactsForm.contacts_public = !props.contactsPublic;
    contactsForm.put('/admin/photographers/settings', { preserveScroll: true });
}

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const role = ref(props.filters?.role ?? '');

function applyFilters() {
    router.get('/admin/photographers', { search: search.value, status: status.value, role: role.value }, { preserveState: true, replace: true });
}

const showInviteForm = ref(false);
const inviteForm = useForm({
    name: '',
    email: '',
    role: 'photographer',
    revenue_share_percent: 70,
});

function sendInvite() {
    inviteForm.post('/admin/photographers/invite', {
        onSuccess: () => {
            inviteForm.reset();
            showInviteForm.value = false;
        },
    });
}

function resendInvite(invitationId) {
    router.post(`/admin/photographers/invitations/${invitationId}/resend`, {}, { preserveScroll: true });
}

function cancelInvite(invitationId) {
    if (!confirm('Biztosan visszavonod ezt a meghívót?')) return;
    router.delete(`/admin/photographers/invitations/${invitationId}`, { preserveScroll: true });
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

function roleLabel(role) {
    return { admin: 'Admin', organizer: 'Szervező', photographer: 'Fotós' }[role] ?? role;
}
</script>

<template>
    <Head title="Fotósok" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Fotósok</h1>
            <button type="button" class="rounded-lg bg-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="showInviteForm = !showInviteForm">
                + Új fotós meghívása
            </button>
        </div>

        <!-- Nyilvános elérhetőségek kapcsoló (csak superadmin) -->
        <div class="mt-4 flex flex-wrap items-start justify-between gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
            <div class="max-w-xl">
                <p class="text-sm font-semibold text-content">Fotós-elérhetőségek a nyilvános „Fotósok" oldalon</p>
                <p class="mt-1 text-xs text-muted">
                    Ha bekapcsolod, a fotósok céges e-mailje, weboldala és közösségi linkjei megjelennek a
                    <a href="/photographers" target="_blank" class="text-accent hover:underline">Fotósaink</a> oldalon.
                    <strong class="text-content">Kikapcsolva</strong> csak a profilkép, a név és a bemutatkozó látszik —
                    így a vásárló nem tudja megkerülni az oldalt a fotós közvetlen megkeresésével.
                </p>
            </div>
            <button
                type="button"
                :disabled="contactsForm.processing"
                class="shrink-0 rounded-lg px-4 py-2 text-xs font-semibold uppercase tracking-wide"
                :class="contactsPublic ? 'bg-accent text-white hover:bg-accent-hover' : 'border border-border text-content hover:border-accent'"
                @click="toggleContacts"
            >
                {{ contactsPublic ? '● Megjelennek' : '○ Rejtve' }}
            </button>
        </div>

        <form v-if="showInviteForm" class="mt-4 grid gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="sendInvite">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Név</span>
                <input v-model="inviteForm.name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                <p v-if="inviteForm.errors.name" class="mt-1 text-xs text-accent">{{ inviteForm.errors.name }}</p>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">E-mail</span>
                <input v-model="inviteForm.email" type="email" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-accent">{{ inviteForm.errors.email }}</p>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szerepkör</span>
                <select v-model="inviteForm.role" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="photographer">Fotós</option>
                    <option value="admin">Adminisztrátor</option>
                    <option value="organizer">Szervező</option>
                </select>
            </label>
            <label class="block" :class="inviteForm.role !== 'photographer' && 'opacity-50'">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Jutalék (%)</span>
                <input v-model.number="inviteForm.revenue_share_percent" type="number" min="0" max="100" :disabled="inviteForm.role !== 'photographer'" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="submit" :disabled="inviteForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Meghívó küldése
                </button>
            </div>
        </form>

        <div v-if="pendingInvitations.length > 0" class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Függőben lévő meghívók</h2>
            <div class="mt-2 overflow-x-auto rounded-[var(--radius-base)] border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Név</th>
                            <th class="px-4 py-2.5 font-semibold">E-mail</th>
                            <th class="px-4 py-2.5 font-semibold">Szerepkör</th>
                            <th class="px-4 py-2.5 font-semibold">Meghívta</th>
                            <th class="px-4 py-2.5 font-semibold">Lejár</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invitation in pendingInvitations" :key="invitation.id">
                            <td class="px-4 py-2.5 text-content">{{ invitation.name }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ invitation.email }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ roleLabel(invitation.role) }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ invitation.invited_by }}</td>
                            <td class="px-4 py-2.5" :class="invitation.is_expired ? 'text-accent' : 'text-muted'">
                                {{ invitation.is_expired ? 'Lejárt' : new Date(invitation.expires_at).toLocaleDateString('hu-HU') }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="resendInvite(invitation.id)">
                                    Újraküldés
                                </button>
                                <button type="button" class="ml-3 text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="cancelInvite(invitation.id)">
                                    Visszavonás
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Keresés</span>
                <input v-model="search" type="text" placeholder="Név vagy e-mail…" class="rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Státusz</span>
                <select v-model="status" class="appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes</option>
                    <option value="active">Aktív</option>
                    <option value="inactive">Inaktív</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szerepkör</span>
                <select v-model="role" class="appearance-none rounded-lg border border-border bg-surface-1 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="">Összes</option>
                    <option value="photographer">Fotós</option>
                    <option value="admin">Admin</option>
                    <option value="organizer">Szervező</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg border border-border px-5 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent">
                Szűrés
            </button>
        </form>

        <div class="mt-4 overflow-x-auto rounded-[var(--radius-base)] border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Név</th>
                        <th class="px-4 py-3 font-semibold">E-mail</th>
                        <th class="px-4 py-3 font-semibold">Szerepkör</th>
                        <th class="px-4 py-3 font-semibold">Regisztráció</th>
                        <th class="px-4 py-3 font-semibold">Média</th>
                        <th class="px-4 py-3 font-semibold">Bevétel</th>
                        <th class="px-4 py-3 font-semibold">Nyitott jutalék</th>
                        <th class="px-4 py-3 font-semibold">Státusz</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-surface-1">
                        <td class="px-4 py-3">
                            <Link :href="user.role === 'organizer' ? '/admin/organizer-payouts' : `/admin/photographers/${user.id}`" class="font-medium text-content hover:text-accent">{{ user.name }}</Link>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ user.email }}</td>
                        <td class="px-4 py-3 text-muted">{{ roleLabel(user.role) }}</td>
                        <td class="px-4 py-3 text-muted">{{ user.created_at }}</td>
                        <td class="px-4 py-3 text-muted">{{ user.media_count }}</td>
                        <td class="px-4 py-3 text-muted">{{ huf(user.revenue_cents) }}</td>
                        <td class="px-4 py-3">
                            <span v-if="user.role === 'photographer'" :class="user.outstanding_cents > 0 ? 'font-semibold text-accent' : 'text-muted'">
                                {{ huf(user.outstanding_cents) }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="user.is_active ? 'border-accent text-accent' : 'border-border text-muted'">
                                    {{ user.is_active ? 'Aktív' : 'Inaktív' }}
                                </span>
                                <span v-if="user.role !== 'organizer' && !user.is_public" class="rounded-full border border-border px-2 py-0.5 text-[11px] font-medium text-muted" title="Nem látszik a nyilvános Fotósaink oldalon">
                                    Rejtett
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="user.role === 'organizer' ? '/admin/organizer-payouts' : `/admin/photographers/${user.id}`" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">
                                Megtekintés
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td colspan="9" class="px-4 py-10 text-center text-sm text-muted">Nincs a szűrésnek megfelelő felhasználó.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Kifizetetlen jutalék összesítő -->
        <div class="mt-3 flex flex-wrap gap-6 rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3 text-sm">
            <div>
                <span class="text-xs uppercase tracking-wide text-muted">Kifizetetlen jutalék összesen</span>
                <span class="ml-2 font-semibold" :class="payoutTotals.outstanding_cents > 0 ? 'text-accent' : 'text-content'">{{ huf(payoutTotals.outstanding_cents) }}</span>
            </div>
            <div>
                <span class="text-xs uppercase tracking-wide text-muted">Eddig kifizetve</span>
                <span class="ml-2 font-semibold text-content">{{ huf(payoutTotals.paid_cents) }}</span>
            </div>
            <p class="basis-full text-[11px] text-muted">A fotós nevére kattintva látod a neki járó jutalékot és készíthetsz kifizetési bizonylatot.</p>
        </div>
    </AdminLayout>
</template>
