<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    payments: { type: Object, required: true },
    mail: { type: Object, required: true },
    captcha: { type: Object, required: true },
    invoicing: { type: Object, required: true },
    monitoring: { type: Object, required: true },
    identity: { type: Object, required: true },
});

const overall = computed(() => {
    const s = props.groups.map((g) => g.summary);
    if (s.includes('critical')) return 'critical';
    if (s.includes('warning')) return 'warning';
    return 'ok';
});

const badge = {
    ok: { label: 'Rendben', cls: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400', dot: 'bg-emerald-400' },
    warning: { label: 'Figyelem', cls: 'border-amber-500/40 bg-amber-500/10 text-amber-400', dot: 'bg-amber-400' },
    critical: { label: 'Hiányzik', cls: 'border-red-500/50 bg-red-500/10 text-red-500', dot: 'bg-red-500' },
};

const paymentForm = useForm({
    default_provider: props.payments.default_provider,
    stripe_enabled: props.payments.stripe_enabled,
    stripe_publishable: props.payments.stripe_publishable,
    stripe_secret: '',
    stripe_webhook_secret: '',
    simplepay_enabled: props.payments.simplepay_enabled,
    simplepay_merchant: props.payments.simplepay_merchant,
    simplepay_secret_key: '',
    simplepay_sandbox: props.payments.simplepay_sandbox,
    barion_enabled: props.payments.barion_enabled,
    barion_payee: props.payments.barion_payee,
    barion_pos_key: '',
    barion_sandbox: props.payments.barion_sandbox,
    clear: [],
});

function savePayments() {
    paymentForm.put('/admin/settings/critical/payments', {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.stripe_secret = '';
            paymentForm.stripe_webhook_secret = '';
            paymentForm.simplepay_secret_key = '';
            paymentForm.barion_pos_key = '';
            paymentForm.clear = [];
        },
    });
}

const mailForm = useForm({
    mailer: props.mail.mailer === 'smtp' ? 'smtp' : (props.mail.mailer === 'log' ? 'log' : 'smtp'),
    host: props.mail.host,
    port: props.mail.port,
    encryption: props.mail.encryption,
    username: props.mail.username,
    password: '',
    clear_password: false,
    from_address: props.mail.from_address,
    from_name: props.mail.from_name,
    reply_to: props.mail.reply_to,
});

function saveMail() {
    mailForm.put('/admin/settings/critical/mail', {
        preserveScroll: true,
        onSuccess: () => {
            mailForm.password = '';
            mailForm.clear_password = false;
        },
    });
}

const testMailForm = useForm({ to: '' });
function sendTestMail() {
    testMailForm.post('/admin/settings/critical/mail/test', { preserveScroll: true });
}

const captchaForm = useForm({
    enabled: props.captcha.enabled,
    site_key: props.captcha.site_key,
    secret: '',
    clear_secret: false,
});

function saveCaptcha() {
    captchaForm.put('/admin/settings/critical/captcha', {
        preserveScroll: true,
        onSuccess: () => {
            captchaForm.secret = '';
            captchaForm.clear_secret = false;
        },
    });
}

const invoiceForm = useForm({
    provider: props.invoicing.provider,
    api_key: '',
    clear_api_key: false,
    block_id: props.invoicing.block_id ?? '',
    vat: props.invoicing.vat,
    auto: props.invoicing.auto,
});

function saveInvoicing() {
    invoiceForm.put('/admin/settings/critical/invoicing', {
        preserveScroll: true,
        onSuccess: () => {
            invoiceForm.api_key = '';
            invoiceForm.clear_api_key = false;
        },
    });
}

const monitoringForm = useForm({
    webhook_url: '',
    clear_webhook: false,
    notify_email: props.monitoring.notify_email,
});

function saveMonitoring() {
    monitoringForm.put('/admin/settings/critical/monitoring', {
        preserveScroll: true,
        onSuccess: () => {
            monitoringForm.webhook_url = '';
            monitoringForm.clear_webhook = false;
        },
    });
}

const backupRunning = ref(false);
function runBackup() {
    backupRunning.value = true;
    router.post('/admin/settings/critical/backup', {}, {
        preserveScroll: true,
        onFinish: () => { backupRunning.value = false; },
    });
}

function fmtBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(n) / Math.log(1024));
    return (n / 1024 ** i).toFixed(i ? 1 : 0) + ' ' + u[i];
}
function dt(iso) {
    return iso ? new Date(iso).toLocaleString('hu-HU') : '—';
}

const identityForm = useForm({ domain: props.identity.domain === 'localhost' ? '' : props.identity.domain });

function saveIdentity() {
    identityForm.put('/admin/settings/critical/identity', { preserveScroll: true });
}

const derivedSlug = computed(() => {
    const host = (identityForm.domain || '').replace(/^www\./, '').split('.')[0] || '';
    return host.replace(/[^a-z0-9]/gi, '').toLowerCase();
});

// A kézi-lépések magyarázó blokk konkrét példaértékei — reaktívak a beírt domainre.
const renameTarget = computed(() => (identityForm.domain || '').trim() || 'ujdomain.hu');
const renameSlug = computed(() => derivedSlug.value || 'ujdomain');
const renameEnv = computed(() => ({
    DB_DATABASE: renameSlug.value,
    APP_NAME: props.identity.name,
    APP_URL: `https://${renameTarget.value}`,
    MAIL_FROM_ADDRESS: `noreply@${renameTarget.value}`,
}));

// ── Átnevezés (rebrand) — előnézet + végrehajtás ──────────────────────────
const identityPlan = ref(null);
const identityPreviewing = ref(false);
const identityApplying = ref(false);
const identityPreviewError = ref('');

async function previewIdentity() {
    identityPreviewError.value = '';
    identityPlan.value = null;
    if (!/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i.test(identityForm.domain || '')) {
        identityPreviewError.value = 'Adj meg egy érvényes domaint.';
        return;
    }
    identityPreviewing.value = true;
    try {
        const res = await fetch(`/admin/settings/critical/identity/preview?domain=${encodeURIComponent(identityForm.domain)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error();
        identityPlan.value = await res.json();
    } catch (e) {
        identityPreviewError.value = 'Az előnézet nem sikerült.';
    } finally {
        identityPreviewing.value = false;
    }
}

function applyIdentity() {
    if (!confirm(`Biztosan átírod az összes "${identityPlan.value?.from}" e-mailt és beállítást "${identityPlan.value?.to}"-ra? Ez nem vonható vissza.`)) return;
    identityApplying.value = true;
    router.post('/admin/settings/critical/identity/apply', { domain: identityForm.domain }, {
        preserveScroll: true,
        onFinish: () => { identityApplying.value = false; },
        onSuccess: () => { identityPlan.value = null; },
    });
}
</script>

<template>
    <Head title="Kritikus beállítások" />

    <AdminLayout>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Kritikus beállítások</h1>
            <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="badge[overall].cls">{{ badge[overall].label }}</span>
        </div>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Az oldal működéséhez elengedhetetlen beállítások egy helyen: fizetés, tárhely, e-mail, ütemező,
            médiafeldolgozás és a végleges domain. A fizetési kulcsok itt szerkeszthetők (titkosítva tárolva).
        </p>

        <!-- Állapot-áttekintő -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section v-for="group in groups" :key="group.group" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">{{ group.group }}</h2>
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold" :class="badge[group.summary].cls">{{ badge[group.summary].label }}</span>
                </div>
                <ul class="mt-3 space-y-2.5">
                    <li v-for="item in group.items" :key="item.key" class="flex gap-2.5 text-xs">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="badge[item.status].dot" />
                        <span class="min-w-0 [overflow-wrap:anywhere]">
                            <span class="font-semibold text-content">{{ item.label }}</span>
                            <span class="text-muted"> — {{ item.detail }}</span>
                        </span>
                    </li>
                </ul>
                <a
                    v-if="group.action && group.action.href.startsWith('/')"
                    :href="group.action.href"
                    class="mt-3 inline-block text-[11px] font-semibold uppercase tracking-wide text-accent hover:underline"
                >{{ group.action.label }} →</a>
            </section>
        </div>

        <!-- Fizetés -->
        <form id="payments" class="mt-8 max-w-2xl space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="savePayments">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Fizetési szolgáltatók</h2>
            <p class="text-xs text-muted [overflow-wrap:anywhere]">
                A kulcsok titkosítva a <code>site_settings</code>-be kerülnek — nem kell <code>.env</code>-et szerkeszteni.
                Üresen hagyott titkos mező = a jelenlegi érték marad.<br />
                Stripe webhook URL: <code class="text-content">{{ identity.stripe_webhook_url }}</code><br />
                SimplePay IPN URL: <code class="text-content">{{ identity.ipn_url }}</code><br />
                Barion callback URL: <code class="text-content">{{ identity.barion_callback_url }}</code>
            </p>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Alapértelmezett szolgáltató</span>
                <select v-model="paymentForm.default_provider" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="stripe">Stripe</option>
                    <option value="simplepay">SimplePay</option>
                    <option value="barion">Barion</option>
                </select>
            </label>

            <fieldset class="space-y-3 rounded-lg border border-border p-4">
                <legend class="px-1 text-[11px] font-semibold uppercase tracking-wide text-muted">Stripe {{ payments.stripe_has_secret ? '· beállítva' : '' }}</legend>
                <label class="flex items-center gap-2 text-xs font-semibold text-content">
                    <input v-model="paymentForm.stripe_enabled" type="checkbox" class="accent-[var(--color-accent)]" /> Elérhető a pénztárban
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Publishable key (pk_…)</span>
                    <input v-model="paymentForm.stripe_publishable" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Secret key (sk_…) {{ payments.stripe_has_secret ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                    <input v-model="paymentForm.stripe_secret" type="password" autocomplete="off" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Webhook secret (whsec_…) {{ payments.stripe_has_webhook_secret ? '— beállítva' : '' }}</span>
                    <input v-model="paymentForm.stripe_webhook_secret" type="password" autocomplete="off" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label v-if="payments.stripe_has_secret" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="paymentForm.clear" type="checkbox" value="stripe_secret" class="accent-[var(--color-accent)]" /> Stripe secret törlése
                </label>
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-border p-4">
                <legend class="px-1 text-[11px] font-semibold uppercase tracking-wide text-muted">SimplePay {{ payments.simplepay_has_secret_key ? '· beállítva' : '' }}</legend>
                <label class="flex items-center gap-2 text-xs font-semibold text-content">
                    <input v-model="paymentForm.simplepay_enabled" type="checkbox" class="accent-[var(--color-accent)]" /> Elérhető a pénztárban
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Merchant azonosító</span>
                    <input v-model="paymentForm.simplepay_merchant" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Secret key {{ payments.simplepay_has_secret_key ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                    <input v-model="paymentForm.simplepay_secret_key" type="password" autocomplete="off" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="paymentForm.simplepay_sandbox" type="checkbox" class="accent-[var(--color-accent)]" /> SANDBOX (teszt) mód
                </label>
                <label v-if="payments.simplepay_has_secret_key" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="paymentForm.clear" type="checkbox" value="simplepay_secret_key" class="accent-[var(--color-accent)]" /> SimplePay secret key törlése
                </label>
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-border p-4">
                <legend class="px-1 text-[11px] font-semibold uppercase tracking-wide text-muted">Barion {{ payments.barion_has_pos_key ? '· beállítva' : '' }}</legend>
                <label class="flex items-center gap-2 text-xs font-semibold text-content">
                    <input v-model="paymentForm.barion_enabled" type="checkbox" class="accent-[var(--color-accent)]" /> Elérhető a pénztárban
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Kifizetési e-mail (a Barion fiók e-mail címe / Payee)</span>
                    <input v-model="paymentForm.barion_payee" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">POSKey {{ payments.barion_has_pos_key ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                    <input v-model="paymentForm.barion_pos_key" type="password" autocomplete="off" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="paymentForm.barion_sandbox" type="checkbox" class="accent-[var(--color-accent)]" /> SANDBOX (teszt) mód
                </label>
                <label v-if="payments.barion_has_pos_key" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="paymentForm.clear" type="checkbox" value="barion_pos_key" class="accent-[var(--color-accent)]" /> Barion POSKey törlése
                </label>
            </fieldset>

            <button type="submit" :disabled="paymentForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Fizetési beállítások mentése
            </button>
        </form>

        <!-- E-mail küldés -->
        <form id="mail" class="mt-8 max-w-2xl space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveMail">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">E-mail küldés (SMTP)</h2>
            <p class="text-xs text-muted [overflow-wrap:anywhere]">
                A rendszer e-mailjei (vásárlási visszaigazoló + letöltési link, jelszó-visszaállítás, fotós-meghívó, GDPR-megerősítő).
                A jelszó titkosítva a <code>site_settings</code>-be kerül — nem kell <code>.env</code>-et szerkeszteni.
                Üresen hagyott jelszó = a jelenlegi marad.
                <span v-if="mail.env_fallback" class="text-content"><br />Jelenleg a <code>.env</code> értékei élnek — mentéssel átveszed az irányítást.</span>
            </p>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Küldési mód</span>
                <select v-model="mailForm.mailer" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="smtp">SMTP (valódi küldés)</option>
                    <option value="log">Log — nem küld valódi levelet (indulás előtti / teszt mód)</option>
                </select>
            </label>

            <fieldset v-if="mailForm.mailer === 'smtp'" class="space-y-3 rounded-lg border border-border p-4">
                <legend class="px-1 text-[11px] font-semibold uppercase tracking-wide text-muted">SMTP szerver</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-[11px] text-muted">Host</span>
                        <input v-model="mailForm.host" type="text" placeholder="pl. smtp.eu.mailgun.org" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="mailForm.errors.host" class="mt-1 text-xs text-red-500">{{ mailForm.errors.host }}</p>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">Port</span>
                        <input v-model="mailForm.port" type="number" placeholder="587" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">Titkosítás</span>
                        <select v-model="mailForm.encryption" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                            <option value="tls">STARTTLS (587)</option>
                            <option value="ssl">SSL/TLS (465)</option>
                            <option value="none">Nincs</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">Felhasználónév</span>
                        <input v-model="mailForm.username" type="text" autocomplete="off" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">Jelszó {{ mail.has_password ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                        <input v-model="mailForm.password" type="password" autocomplete="new-password" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                </div>
                <label v-if="mail.has_password" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="mailForm.clear_password" type="checkbox" class="accent-[var(--color-accent)]" /> SMTP jelszó törlése
                </label>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Feladó cím</span>
                    <input v-model="mailForm.from_address" type="email" placeholder="noreply@sajat-domain.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="mailForm.errors.from_address" class="mt-1 text-xs text-red-500">{{ mailForm.errors.from_address }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Feladó név</span>
                    <input v-model="mailForm.from_name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-[11px] text-muted">Válasz (Reply-To) cím — ide válaszol a vevő</span>
                    <input v-model="mailForm.reply_to" type="email" placeholder="kapcsolat@sajat-domain.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    <p class="mt-1 text-[11px] text-muted">
                        A rendszer-e-mailek feladója maradhat <code>noreply@…</code>, de ha a vevő „Válasz"-t nyom (pl. a fotósnak írt kérdésre kapott válaszra), az erre a figyelt címre megy. Üresen hagyva a feladó címre válaszol.
                    </p>
                    <p v-if="mailForm.errors.reply_to" class="mt-1 text-xs text-red-500">{{ mailForm.errors.reply_to }}</p>
                </label>
            </div>

            <button type="submit" :disabled="mailForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                E-mail beállítások mentése
            </button>

            <div class="mt-2 flex flex-wrap items-end gap-2 border-t border-border pt-4">
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Tesztlevél ide</span>
                    <input v-model="testMailForm.to" type="email" placeholder="te@pelda.hu" class="w-56 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <button type="button" :disabled="testMailForm.processing || !testMailForm.to" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-40" @click="sendTestMail">
                    {{ testMailForm.processing ? 'Küldés…' : 'Tesztlevél küldése' }}
                </button>
                <p class="w-full text-[11px] text-muted">A tesztlevél a jelenleg elmentett beállításokat használja — előbb ments.</p>
            </div>
        </form>

        <!-- Captcha (hCaptcha) a Kapcsolat űrlaphoz -->
        <form id="captcha" class="mt-8 max-w-2xl space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveCaptcha">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Captcha — hCaptcha</h2>
            <p class="text-xs text-muted [overflow-wrap:anywhere]">
                Bekapcsolva a <strong class="text-content">Kapcsolat</strong> űrlapon (és a média-oldali „Kérdés a fotóshoz"-nál)
                a hCaptcha widget váltja a beépített számtani kérdést. A többi védelem (time-trap, proof-of-work, honeypot, rate limit)
                változatlanul marad. A kulcsokat a <a href="https://dashboard.hcaptcha.com/sites" target="_blank" class="text-accent hover:underline">hCaptcha dashboardon</a> kapod
                (van „mindig sikeres" teszt-kulcspár is a fejlesztéshez). A secret titkosítva a <code>site_settings</code>-be kerül.
            </p>

            <label class="flex items-center gap-2 text-xs font-semibold text-content">
                <input v-model="captchaForm.enabled" type="checkbox" class="accent-[var(--color-accent)]" /> hCaptcha bekapcsolva
                <span v-if="captchaForm.enabled && (!captchaForm.site_key || !captcha.has_secret)" class="font-normal text-amber-400">— site key + secret is kell hozzá</span>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] text-muted">Site key</span>
                <input v-model="captchaForm.site_key" type="text" placeholder="10000000-ffff-ffff-ffff-000000000001 (teszt)" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] text-muted">Secret key {{ captcha.has_secret ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                <input v-model="captchaForm.secret" type="password" autocomplete="new-password" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>

            <label v-if="captcha.has_secret" class="flex items-center gap-2 text-[11px] text-muted">
                <input v-model="captchaForm.clear_secret" type="checkbox" class="accent-[var(--color-accent)]" /> Secret key törlése
            </label>

            <button type="submit" :disabled="captchaForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Captcha beállítások mentése
            </button>
        </form>

        <!-- Számlázás -->
        <form class="mt-8 max-w-2xl space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveInvoicing">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Számlázás</h2>
            <p class="text-xs text-muted">
                Magyar webshopnál az online kártyás eladásról <strong class="text-content">számla</strong> kötelező (nyugta nem elég), és
                a NAV Online Számlának is jelenteni kell. Ezt a <strong class="text-content">Billingo</strong> intézi — itt csak az API kulcsot
                és a számlatömböt kell megadni. Bekapcsolva a pénztár bekéri a vevő nevét + országát.
            </p>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szolgáltató</span>
                <select v-model="invoiceForm.provider" class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                    <option value="none">Nincs (nem állít ki számlát)</option>
                    <option value="billingo">Billingo</option>
                </select>
            </label>

            <template v-if="invoiceForm.provider === 'billingo'">
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">Billingo API kulcs (v3) {{ invoicing.has_api_key ? '— beállítva, felülíráshoz add meg újra' : '' }}</span>
                    <input v-model="invoiceForm.api_key" type="password" autocomplete="off" placeholder="••••••••" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <label v-if="invoicing.has_api_key" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="invoiceForm.clear_api_key" type="checkbox" class="accent-[var(--color-accent)]" /> API kulcs törlése
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">Számlatömb (block) azonosító</span>
                        <input v-model="invoiceForm.block_id" type="number" min="1" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] text-muted">ÁFA-jelölés</span>
                        <select v-model="invoiceForm.vat" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                            <option v-for="c in invoicing.vat_codes" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </label>
                </div>
                <label class="flex items-center gap-2 text-xs font-semibold text-content">
                    <input v-model="invoiceForm.auto" type="checkbox" class="accent-[var(--color-accent)]" /> Automatikus számla a sikeres fizetés után
                </label>
            </template>

            <button type="submit" :disabled="invoiceForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Számlázási beállítások mentése
            </button>

            <div class="mt-2 space-y-2 rounded-lg border border-border bg-surface-2/40 p-4 text-[11px] leading-relaxed text-muted">
                <p class="font-semibold text-content">ÁFA és a vevő országa — röviden</p>
                <p>
                    A fotók/videók letöltése <strong class="text-content">elektronikus szolgáltatás</strong>, aminél B2C-nél a teljesítés helye
                    a vevő országa.
                </p>
                <ul class="list-disc space-y-1 pl-4">
                    <li><strong class="text-content">Alanyi ÁFA-mentes (12 M Ft/év bevétel alatt)</strong> → minden számla ÁFA-mentes (AAM), belföldi és EU-s vevőnek is. Ekkor az „ÁFA-jelölés" maradjon <code>AAM</code>.</li>
                    <li><strong class="text-content">EU-s magánszemély + 10 000 €/év alatt</strong> az összes EU-s digitális eladásod → magyar szabály, semmi extra.</li>
                    <li><strong class="text-content">EU-s magánszemély + 10 000 € fölött</strong> → a vevő országának ÁFA-ját kell felszámítani, ehhez <strong class="text-content">OSS</strong> regisztráció kell a NAV-nál (negyedéves bevallás). Ezt könyvelővel állítsd be.</li>
                    <li><strong class="text-content">EU-n kívüli vevő</strong> (USA, UK, svájci turista) → az ügylet az ÁFA területi hatályán kívül esik (a rendszer automatikusan „EU-N KÍVÜLI" jelöléssel állítja ki).</li>
                </ul>
                <p>A pontos beállítást a könyvelőd erősítse meg — a rendszer alapból mindenre az itt megadott jelölést használja (EU-n kívülit kivéve).</p>
            </div>
        </form>

        <!-- Monitoring és mentés -->
        <div class="mt-8 max-w-2xl space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Monitoring és mentés</h2>

            <form class="space-y-3" @submit.prevent="saveMonitoring">
                <p class="text-xs text-muted">
                    Kezeletlen hiba esetén a rendszer rögzíti a <a href="/admin/errors" class="text-accent hover:underline">Hibanaplóba</a>,
                    és — ha be van kapcsolva — értesíti a superadminokat. Ugyanarra a hibára csak ritkán megy értesítés (nincs spam).
                </p>
                <label class="flex items-center gap-2 text-xs font-semibold text-content">
                    <input v-model="monitoringForm.notify_email" type="checkbox" class="accent-[var(--color-accent)]" /> E-mail értesítés új hibánál
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] text-muted">
                        Webhook URL (opcionális — Slack / Discord „incoming webhook", titkosítva tárolva)
                        {{ monitoring.has_webhook ? '— beállítva, felülíráshoz add meg újra' : '' }}
                    </span>
                    <input v-model="monitoringForm.webhook_url" type="url" autocomplete="off" placeholder="https://hooks.slack.com/services/…" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="monitoringForm.errors.webhook_url" class="mt-1 text-xs text-red-500">{{ monitoringForm.errors.webhook_url }}</p>
                </label>
                <label v-if="monitoring.has_webhook" class="flex items-center gap-2 text-[11px] text-muted">
                    <input v-model="monitoringForm.clear_webhook" type="checkbox" class="accent-[var(--color-accent)]" /> Webhook törlése
                </label>
                <button type="submit" :disabled="monitoringForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Monitoring mentése
                </button>
            </form>

            <div class="border-t border-border pt-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-xs">
                        <span class="font-semibold text-content">Adatbázis-mentés</span>
                        <span class="text-muted"> — disk: <code>{{ monitoring.backup_disk }}</code>, napi 03:15 (cron)</span>
                        <p class="mt-0.5 text-muted">
                            <template v-if="monitoring.backup.status === 'ok'">Utolsó sikeres: {{ dt(monitoring.backup.at) }}</template>
                            <template v-else-if="monitoring.backup.status === 'failed'"><span class="text-red-500">Utolsó futás HIBA ({{ dt(monitoring.backup.at) }}): {{ monitoring.backup.error }}</span></template>
                            <template v-else>Még nem futott mentés.</template>
                        </p>
                    </div>
                    <button type="button" :disabled="backupRunning" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent disabled:opacity-60" @click="runBackup">
                        {{ backupRunning ? 'Mentés…' : 'Mentés most' }}
                    </button>
                </div>
                <ul v-if="monitoring.backups.length" class="mt-3 space-y-1 text-[11px]">
                    <li v-for="b in monitoring.backups" :key="b.name" class="flex items-center justify-between gap-2 rounded border border-border px-2.5 py-1.5">
                        <span class="font-mono text-content [overflow-wrap:anywhere]">{{ b.name }}</span>
                        <span class="flex shrink-0 items-center gap-3 text-muted">
                            {{ fmtBytes(b.size) }}
                            <a :href="`/admin/settings/critical/backup/${b.name}`" class="font-semibold uppercase tracking-wide text-accent hover:underline">Letöltés</a>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Végleges domain / átnevezés -->
        <div class="mt-8 max-w-2xl space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Az oldal neve / átnevezés</h2>
            <p class="text-xs text-muted">
                Jelenlegi rendszer-domain: <code class="text-content">{{ identity.current_domain }}</code> ·
                adatbázis: <code class="text-content">{{ identity.db_name }}</code>.
            </p>
            <p class="text-xs text-muted">
                Add meg az új domaint és kattints az <strong class="text-content">Előnézet</strong>re — látni fogod pontosan, mi változna.
                Az átnevezés <strong class="text-content">két részből</strong> áll:
            </p>
            <ul class="ml-4 list-disc space-y-1 text-xs text-muted">
                <li><strong class="text-content">Automatikus rész</strong> (egy gombnyomás itt): a rendszer-e-mailek (superadmin, fotósok, rendeléshez/feliratkozáshoz kötött címek) és a beállítások átírása.</li>
                <li><strong class="text-content">Kézi rész</strong> (a szerveren, lépésről lépésre — az előnézet leírja): az adatbázis átnevezése és a <code>.env</code> fájl 4 sora.</li>
            </ul>
            <p class="text-xs text-muted">
                A látogatóknak megjelenő szövegeket (Rólunk oldal, GYIK) a saját szerkesztőoldalaikon módosítsd.
            </p>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Új domain</span>
                <input
                    v-model="identityForm.domain"
                    type="text"
                    placeholder="pelda.hu"
                    class="w-full max-w-sm rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"
                />
                <p v-if="identityForm.errors.domain" class="mt-1 text-xs text-accent">{{ identityForm.errors.domain }}</p>
                <p v-else-if="derivedSlug" class="mt-1 text-[11px] text-muted">Azonosító / adatbázisnév ebből: <span class="font-semibold text-content">{{ derivedSlug }}</span></p>
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="button" :disabled="identityForm.processing" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent disabled:opacity-60" @click="saveIdentity">
                    Csak a domain mentése
                </button>
                <button type="button" :disabled="identityPreviewing" class="rounded-lg border border-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-accent hover:bg-accent hover:text-white disabled:opacity-60" @click="previewIdentity">
                    {{ identityPreviewing ? 'Előnézet…' : 'Előnézet' }}
                </button>
            </div>
            <p v-if="identityPreviewError" class="text-xs text-accent">{{ identityPreviewError }}</p>

            <!-- Előnézet eredménye -->
            <div v-if="identityPlan" class="space-y-3 rounded-lg border border-border bg-surface-2/50 p-4 text-xs">
                <p class="font-semibold text-content">
                    {{ identityPlan.from }} → {{ identityPlan.to }}
                    <span class="text-muted">({{ identityPlan.from_slug }} → {{ identityPlan.to_slug }})</span>
                </p>

                <p v-if="identityPlan.noop" class="rounded border border-emerald-500/40 bg-emerald-500/5 p-2 text-emerald-400">
                    Ez a domain már be van állítva — nincs teendő. Ha ténylegesen más névre akarsz váltani, írj be egy MÁS domaint.
                </p>

                <template v-else>
                    <div>
                        <p class="font-semibold text-content">E-mailek ({{ identityPlan.emails.length }})</p>
                        <ul v-if="identityPlan.emails.length" class="mt-1 space-y-0.5 text-muted">
                            <li v-for="(c, i) in identityPlan.emails" :key="i" class="[overflow-wrap:anywhere]">
                                <code>{{ c.table }}.{{ c.column }}</code>: {{ c.old }} → <span class="text-content">{{ c.new }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-1 text-muted">Nincs átírandó e-mail.</p>
                    </div>

                    <div>
                        <p class="font-semibold text-content">Beállítások ({{ identityPlan.settings.length }})</p>
                        <ul v-if="identityPlan.settings.length" class="mt-1 space-y-0.5 text-muted">
                            <li v-for="(c, i) in identityPlan.settings" :key="i" class="[overflow-wrap:anywhere]">
                                <code>{{ c.key }}</code>: {{ c.old || '—' }} → <span class="text-content">{{ c.new }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-1 text-muted">Nincs átírandó beállítás.</p>
                    </div>

                    <button type="button" :disabled="identityApplying" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60" @click="applyIdentity">
                        {{ identityApplying ? 'Átnevezés…' : 'Az automatikus rész végrehajtása (e-mailek + beállítások)' }}
                    </button>

                    <!-- Kód-találatok -->
                    <div v-if="identityPlan.code_hits.length" class="rounded border border-amber-500/40 bg-amber-500/5 p-3">
                        <p class="font-semibold text-amber-400">{{ identityPlan.code_hits.length }} említés a forráskódban</p>
                        <p class="mt-1 text-muted">
                            Ezeket a rendszer NEM tudja átírni (parancsnevek, kommentek a program kódjában). A látogatók
                            <strong class="text-content">nem látják</strong>, de a 100%-os átnevezéshez egy fejlesztőnek ki kell
                            cserélnie a <code>{{ identityPlan.from_slug }}</code> szót <code>{{ identityPlan.to_slug }}</code>-ra
                            ezekben a fájlokban. Minden sor: <code>fájl:sorszám  a szöveg</code>.
                        </p>
                        <ul class="mt-2 max-h-32 space-y-0.5 overflow-y-auto text-muted">
                            <li v-for="(h, i) in identityPlan.code_hits" :key="i" class="font-mono [overflow-wrap:anywhere]">{{ h }}</li>
                        </ul>
                    </div>
                </template>
            </div>

            <!-- Kézi lépések — MINDIG látható útmutató (a beírt domainre igazodó példákkal) -->
            <details class="rounded-lg border border-border bg-surface-2/40 p-4 text-xs" open>
                <summary class="cursor-pointer font-semibold text-content">
                    A szerveren, kézzel elvégzendő lépések (útmutató)
                </summary>
                <p class="mt-2 text-muted">
                    Ezt a részt a webfelület nem tudja megcsinálni (leállítaná saját magát). Csináld végig sorban,
                    a szerveren belépve (SSH, vagy a tárhely „terminál" / „fájlkezelő" funkciója). A parancsokat mindig
                    a <strong class="text-content">projekt főmappájában</strong> add ki. A példákban az új név:
                    <code class="text-content">{{ renameTarget }}</code> (azonosító: <code class="text-content">{{ renameSlug }}</code>) —
                    ez a fenti mezőbe beírt domainhez igazodik.
                </p>
                <ol class="mt-3 list-decimal space-y-2.5 pl-4 text-muted">
                    <li>
                        <strong class="text-content">Tedd karbantartás módba az oldalt.</strong><br />
                        Hol: a szerver termináljában.<br />
                        Parancs: <code>php artisan down</code><br />
                        Miért: az adatbázist nem lehet átnevezni, amíg dolgozik rajta az oldal.
                    </li>
                    <li>
                        <strong class="text-content">Nevezd át az adatbázist</strong>
                        (<code>{{ identity.db_name }}</code> → <code>{{ renameSlug }}</code>).<br />
                        Hol: adatbázis-kezelőben (pgAdmin / Adminer), VAGY terminálban a <code>psql</code>-lel —
                        de <strong class="text-content">ne</strong> a most átnevezendő adatbázishoz csatlakozva
                        (lépj be helyette a <code>postgres</code> nevű adatbázisba).<br />
                        Parancs: <code>ALTER DATABASE {{ identity.db_name }} RENAME TO {{ renameSlug }};</code>
                    </li>
                    <li>
                        <strong class="text-content">Írd át a <code>.env</code> fájlt.</strong><br />
                        Hol: a projekt főmappájában van egy <code>.env</code> nevű fájl (a pont is a neve része, gyakran rejtett).
                        Nyisd meg szövegszerkesztővel: <code>nano .env</code>, vagy a tárhely fájlkezelőjében „szerkesztés".<br />
                        Mit: keresd meg ezt a 4 sort, és állítsd pontosan így (a régi értéket töröld, ez jöjjön a helyére):
                        <ul class="mt-1 space-y-0.5">
                            <li v-for="(v, k) in renameEnv" :key="k"><code>{{ k }}="{{ v }}"</code></li>
                        </ul>
                        Ha valamelyik sor nincs a fájlban, írd be új sorként. Végül mentsd el.
                    </li>
                    <li>
                        <strong class="text-content">Ürítsd a gyorsítótárat.</strong><br />
                        Parancs: <code>php artisan config:clear</code>
                    </li>
                    <li>
                        <strong class="text-content">Indítsd újra a háttérfolyamatokat.</strong><br />
                        Parancs: <code>sudo supervisorctl restart all</code> (ha supervisor kezeli), vagy
                        <code>sudo systemctl restart &lt;szolgáltatás&gt;</code>.
                        Ha még nincs beállított háttér-worker / ütemező, ezt a lépést hagyd ki.
                    </li>
                    <li>
                        <strong class="text-content">Kapcsold vissza az oldalt.</strong><br />
                        Parancs: <code>php artisan up</code>
                    </li>
                    <li>
                        <strong class="text-content">Ellenőrizd, hogy nem maradt régi nyom.</strong><br />
                        Parancs: <code>php artisan kanyarfotozas:audit-identity</code><br />
                        Ha „nincs nyom" üzenettel tér vissza → kész.
                        Ha fájlokat sorol fel → azokat a fenti „említés a forráskódban" doboz szerint egy fejlesztő javítja.
                    </li>
                </ol>
            </details>
        </div>
    </AdminLayout>
</template>
