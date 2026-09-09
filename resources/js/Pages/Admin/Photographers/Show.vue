<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useChartColors } from '@/Composables/useChartColors';
import { useMediaUrl } from '@/Composables/useMediaUrl';
import '@/chartSetup';

const { mediaUrl } = useMediaUrl();

const props = defineProps({
    photographer: Object,
    kpis: Object,
    revenueTrend: Object,
    events: Array,
    sales: Array,
    activity: Array,
    payout: { type: Object, default: null },
});

const tab = ref('overview');
const { colors } = useChartColors();

const revenueTrendData = computed(() => ({
    labels: props.revenueTrend.labels,
    datasets: [{
        label: 'Bevétel (Ft)',
        data: props.revenueTrend.data,
        borderColor: colors.value.accent,
        backgroundColor: colors.value.accent + '33',
        tension: 0.3,
        fill: true,
    }],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: colors.value.muted } } },
    scales: {
        x: { ticks: { color: colors.value.muted }, grid: { color: colors.value.border } },
        y: { ticks: { color: colors.value.muted }, grid: { color: colors.value.border } },
    },
}));

const form = useForm({
    name: props.photographer.name,
    email: props.photographer.email,
    role: props.photographer.role,
    revenue_share_percent: props.photographer.revenue_share_percent,
    is_active: props.photographer.is_active,
    is_public: props.photographer.is_public ?? false,
    bio: props.photographer.bio ?? '',
    public_email: props.photographer.public_email ?? '',
    website: props.photographer.website ?? '',
    social_instagram: props.photographer.social_instagram ?? '',
    social_facebook: props.photographer.social_facebook ?? '',
    social_youtube: props.photographer.social_youtube ?? '',
    social_tiktok: props.photographer.social_tiktok ?? '',
    avatar: null,
    remove_avatar: false,
});

const avatarInput = ref(null);
const avatarPreview = ref(null);

function pickAvatar(e) {
    const file = e.target.files?.[0] ?? null;
    form.avatar = file;
    form.remove_avatar = false;
    avatarPreview.value = file ? URL.createObjectURL(file) : null;
}

function clearAvatar() {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    if (avatarInput.value) avatarInput.value.value = '';
}

function saveProfile() {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(`/admin/photographers/${props.photographer.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.avatar = null;
                form.remove_avatar = false;
                avatarPreview.value = null;
                if (avatarInput.value) avatarInput.value.value = '';
            },
        });
}

function resetPassword() {
    if (!confirm('Ideiglenes jelszót küldünk e-mailben — biztosan folytatod?')) return;
    router.post(`/admin/photographers/${props.photographer.id}/reset-password`, {}, { preserveScroll: true });
}

function deletePhotographer() {
    if (!confirm('Biztosan törlöd ezt a felhasználót? Ez csak akkor sikerül, ha nincs feltöltött médiája.')) return;
    router.delete(`/admin/photographers/${props.photographer.id}`);
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}

function dt(value) {
    return value ? new Date(value).toLocaleDateString('hu-HU') : '—';
}

const activityDetail = ref(null);

// --- Kifizetések fül ---
const statusLabel = {
    pending: 'Nyitott',
    paid: 'Kifizetve',
    reversed: 'Visszavonva',
    draft: 'Előkészített',
};

const createForm = useForm({ until: '', method: '', reference: '', note: '' });
const payForm = useForm({ method: '', reference: '', paid_at: '', note: '' });
const payingId = ref(null);

function createPayout() {
    createForm.post(`/admin/photographers/${props.photographer.id}/payout`, {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function openPay(payout) {
    payingId.value = payout.id;
    payForm.reset();
    payForm.method = payout.method || props.payout?.methods?.[0] || '';
}

function submitPay(id) {
    payForm.post(`/admin/payouts/${id}/paid`, {
        preserveScroll: true,
        onSuccess: () => { payingId.value = null; payForm.reset(); },
    });
}

function deleteDraft(id) {
    if (!confirm('Visszavonod ezt az előkészített bizonylatot? A tételek visszakerülnek a nyitott egyenlegbe.')) return;
    router.delete(`/admin/payouts/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="photographer.name" />

    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">{{ photographer.name }}</h1>
                <p class="text-sm text-muted">{{ photographer.email }} · {{ photographer.role === 'admin' ? 'Admin' : 'Fotós' }}</p>
            </div>
            <span class="rounded-full border px-3 py-1 text-xs font-medium" :class="photographer.is_active ? 'border-accent text-accent' : 'border-border text-muted'">
                {{ photographer.is_active ? 'Aktív' : 'Inaktív' }}
            </span>
        </div>

        <div class="mt-4 flex gap-1 border-b border-border">
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-xs font-semibold uppercase tracking-wide"
                :class="tab === 'overview' ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-content'"
                @click="tab = 'overview'"
            >
                Áttekintő
            </button>
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-xs font-semibold uppercase tracking-wide"
                :class="tab === 'activity' ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-content'"
                @click="tab = 'activity'"
            >
                Tevékenység
            </button>
            <button
                v-if="payout"
                type="button"
                class="border-b-2 px-4 py-2 text-xs font-semibold uppercase tracking-wide"
                :class="tab === 'payout' ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-content'"
                @click="tab = 'payout'"
            >
                Kifizetések
            </button>
        </div>

        <div v-if="tab === 'overview'" class="mt-6 space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ kpis.uploaded_photos }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Feltöltött kép</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ kpis.uploaded_videos }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Feltöltött videó</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ kpis.sold_count }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Eladott média</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ huf(kpis.revenue_cents) }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Összes bevétel</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ huf(kpis.average_price_cents) }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Átlagos ár</div>
                </div>
            </div>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Bevételi trend (30 nap)</h2>
                <div class="mt-4 h-56"><Line :data="revenueTrendData" :options="chartOptions" /></div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <form class="space-y-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveProfile">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Profil szerkesztése</h2>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Név</span>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">E-mail</span>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szerepkör</span>
                        <select v-model="form.role" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                            <option value="photographer">Fotós</option>
                            <option value="admin">Adminisztrátor</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Jutalék (%)</span>
                        <input v-model.number="form.revenue_share_percent" type="number" min="0" max="100" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="flex items-center gap-2 text-sm text-content">
                        <input v-model="form.is_active" type="checkbox" class="accent-[var(--color-accent)]" />
                        Aktív fiók
                    </label>
                    <label class="flex items-center gap-2 text-sm text-content">
                        <input v-model="form.is_public" type="checkbox" class="accent-[var(--color-accent)]" />
                        Nyilvános profil (megjelenik a „Fotósok" oldalon)
                    </label>

                    <div class="flex items-center gap-3 pt-1">
                        <img
                            v-if="avatarPreview || (photographer.avatar && !form.remove_avatar)"
                            :src="avatarPreview || mediaUrl(photographer.avatar)"
                            alt=""
                            class="h-16 w-16 shrink-0 rounded-full border border-border object-cover"
                        />
                        <span v-else class="grid h-16 w-16 shrink-0 place-items-center rounded-full border border-dashed border-border text-[10px] text-muted">nincs kép</span>
                        <div class="text-xs">
                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-content"
                                @change="pickAvatar"
                            />
                            <button
                                v-if="avatarPreview || (photographer.avatar && !form.remove_avatar)"
                                type="button"
                                class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover"
                                @click="clearAvatar"
                            >
                                Profilkép törlése
                            </button>
                        </div>
                    </div>
                    <p v-if="form.errors.avatar" class="text-xs text-accent">{{ form.errors.avatar }}</p>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Bemutatkozás (pár mondat — a Fotósok oldalon jelenik meg)</span>
                        <textarea v-model="form.bio" rows="3" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"></textarea>
                    </label>

                    <div class="rounded-lg border border-border bg-surface-2/40 p-3">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted">Nyilvános elérhetőségek (a Fotósok oldalon jelennek meg)</span>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Céges e-mail (a bejelentkezésitől külön)</span>
                                <input v-model="form.public_email" type="email" placeholder="pl. peter@kanyarfotozas.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                                <span v-if="form.errors.public_email" class="mt-1 block text-xs text-accent">{{ form.errors.public_email }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Weboldal</span>
                                <input v-model="form.website" type="text" placeholder="pl. peterfoto.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                            </label>
                        </div>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <input v-model="form.social_facebook" type="text" placeholder="Facebook oldal linkje" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                            <input v-model="form.social_instagram" type="text" placeholder="Instagram profil linkje" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                            <input v-model="form.social_youtube" type="text" placeholder="YouTube csatorna linkje" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                            <input v-model="form.social_tiktok" type="text" placeholder="TikTok oldal linkje" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">Mentés</button>
                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="resetPassword">Jelszó visszaállítása</button>
                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="deletePhotographer">Törlés</button>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Eseményei</h2>
                        <div class="mt-3 space-y-2">
                            <div v-for="event in events" :key="event.id" class="flex items-center justify-between border-b border-border py-2 text-sm last:border-b-0">
                                <span class="text-content">{{ event.name }}</span>
                                <span class="text-[11px] text-muted">{{ event.event_date }} · {{ event.media_count }} média</span>
                            </div>
                            <p v-if="events.length === 0" class="text-sm text-muted">Nincs esemény.</p>
                        </div>
                    </div>

                    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Legutóbbi értékesítései</h2>
                        <div class="mt-3 space-y-2">
                            <div v-for="(sale, i) in sales.slice(0, 10)" :key="i" class="flex items-center justify-between border-b border-border py-2 text-sm last:border-b-0">
                                <span class="text-muted">{{ new Date(sale.order_date).toLocaleDateString('hu-HU') }} · {{ sale.buyer_email_masked }}</span>
                                <span class="text-content">{{ huf(sale.price_cents) }}</span>
                            </div>
                            <p v-if="sales.length === 0" class="text-sm text-muted">Még nincs értékesítés.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="tab === 'activity'" class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Tevékenység napló</h2>
            <div class="mt-3 divide-y divide-border">
                <div v-for="entry in activity" :key="entry.id" class="py-3">
                    <button type="button" class="flex w-full items-center justify-between text-left text-sm" @click="activityDetail = activityDetail === entry.id ? null : entry.id">
                        <span class="text-content">{{ entry.description }} <span class="text-muted">({{ entry.subject_type }})</span></span>
                        <span class="text-[11px] text-muted">{{ new Date(entry.created_at).toLocaleString('hu-HU') }}</span>
                    </button>
                    <pre v-if="activityDetail === entry.id" class="mt-2 overflow-x-auto rounded-lg bg-surface-2 p-3 text-[11px] text-muted">{{ JSON.stringify(entry.properties, null, 2) }}</pre>
                </div>
                <p v-if="activity.length === 0" class="py-3 text-sm text-muted">Nincs rögzített tevékenység.</p>
            </div>
        </div>

        <div v-else-if="tab === 'payout' && payout" class="mt-6 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Fotós kifizetés-elszámolás</h2>
                <a :href="`/admin/photographers/${photographer.id}/payout/export`" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover">
                    Főkönyv CSV
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold" :class="payout.outstanding.amount_cents > 0 ? 'text-accent' : 'text-content'">{{ huf(payout.outstanding.amount_cents) }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Nyitott jutalék</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ payout.outstanding.media_count }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Elszámolatlan tétel</div>
                </div>
                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                    <div class="text-xl font-bold text-content">{{ dt(payout.outstanding.oldest_at) }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-muted">Legrégebbi nyitott tétel</div>
                </div>
            </div>

            <form class="grid gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="createPayout">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-content sm:col-span-2 lg:col-span-4">Kifizetési bizonylat előkészítése</h3>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Eddig (opcionális)</span>
                    <input v-model="createForm.until" type="date" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Mód</span>
                    <select v-model="createForm.method" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="">—</option>
                        <option v-for="m in payout.methods" :key="m" :value="m">{{ m }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Hivatkozás</span>
                    <input v-model="createForm.reference" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Megjegyzés</span>
                    <input v-model="createForm.note" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button
                        type="submit"
                        :disabled="createForm.processing || payout.outstanding.amount_cents === 0"
                        class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                    >
                        Bizonylat előkészítése ({{ huf(payout.outstanding.amount_cents) }})
                    </button>
                </div>
            </form>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-content">Bizonylatok</h3>
                <div class="mt-3 space-y-3">
                    <div v-for="p in payout.history" :key="p.id" class="rounded-lg border border-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-medium text-content">{{ p.payout_number }}</span>
                                <span class="ml-2 rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="p.status === 'paid' ? 'border-accent text-accent' : 'border-border text-muted'">
                                    {{ statusLabel[p.status] ?? p.status }}
                                </span>
                            </div>
                            <div class="text-sm text-content">{{ huf(p.amount_cents) }} · {{ p.media_count }} tétel</div>
                        </div>
                        <div class="mt-1 text-[11px] text-muted">
                            Időszak: {{ dt(p.period_start) }} – {{ dt(p.period_end) }}
                            <template v-if="p.paid_at"> · Kifizetve: {{ dt(p.paid_at) }} ({{ p.method }})</template>
                        </div>

                        <div v-if="p.status === 'draft'" class="mt-3">
                            <div v-if="payingId === p.id" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                <select v-model="payForm.method" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                                    <option v-for="m in payout.methods" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <input v-model="payForm.reference" type="text" placeholder="Hivatkozás" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                                <input v-model="payForm.paid_at" type="date" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                <input v-model="payForm.note" type="text" placeholder="Megjegyzés" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                                <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                                    <button type="button" :disabled="payForm.processing" class="rounded-lg bg-accent px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60" @click="submitPay(p.id)">
                                        Kifizetés rögzítése
                                    </button>
                                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="payingId = null">Mégse</button>
                                </div>
                            </div>
                            <div v-else class="flex flex-wrap gap-3">
                                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="openPay(p)">Kifizetettnek jelöl</button>
                                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="deleteDraft(p.id)">Visszavonás</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="payout.history.length === 0" class="text-sm text-muted">Még nincs kifizetési bizonylat.</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-[var(--radius-base)] border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Dátum</th>
                            <th class="px-4 py-2.5 font-semibold">Rendelés</th>
                            <th class="px-4 py-2.5 font-semibold">Esemény</th>
                            <th class="px-4 py-2.5 font-semibold">Média</th>
                            <th class="px-4 py-2.5 font-semibold">Bruttó</th>
                            <th class="px-4 py-2.5 font-semibold">%</th>
                            <th class="px-4 py-2.5 font-semibold">Jutalék</th>
                            <th class="px-4 py-2.5 font-semibold">Állapot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="e in payout.ledger" :key="e.id">
                            <td class="px-4 py-2.5 text-muted">{{ dt(e.earned_at) }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ e.order_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ e.event ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ e.media_type === 'video' ? 'Videó' : 'Fotó' }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ huf(e.gross_cents) }}</td>
                            <td class="px-4 py-2.5 text-muted">{{ e.share_percent }}%</td>
                            <td class="px-4 py-2.5 text-content">{{ huf(e.amount_cents) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="e.status === 'paid' ? 'border-accent text-accent' : e.status === 'reversed' ? 'border-border text-muted line-through' : 'border-border text-muted'">
                                    {{ statusLabel[e.status] ?? e.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="payout.ledger.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-muted">Nincs jutalék-tétel.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
