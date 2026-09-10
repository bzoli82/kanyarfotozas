<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    configured: Boolean,
    connection: Object,
    stats: Object,
    failingMedia: Array,
    r2: Object,
    r2Configured: Boolean,
    importDisk: String,
    diskRoles: Object,
    archive: Object,
});

// --- Archív disk kapcsoló (megvásárolt teljes méretű fájlok: NAS vagy R2) ---
const DISK_LABEL = { nas: 'Saját NAS / SFTP', r2_private: 'Cloudflare R2' };

const archiveDiskForm = useForm({
    disk: props.archive.overridden ? props.archive.effective : '',
});

function saveArchiveDisk() {
    archiveDiskForm.transform((data) => ({ ...data, _method: 'put', disk: data.disk || null })).post('/admin/settings/storage/archive-disk', {
        preserveScroll: true,
    });
}

const syncForm = useForm({ from: '', to: '' });
const syncStatus = ref(props.archive.sync);
let syncPoll = null;

const syncRunning = computed(() => syncStatus.value && syncStatus.value.running);

function startSync(from, to) {
    if (syncRunning.value) return;
    syncForm.from = from;
    syncForm.to = to;
    syncForm.post('/admin/settings/storage/archive-sync', {
        preserveScroll: true,
        onSuccess: () => pollSync(),
    });
}

async function pollSync() {
    try {
        const res = await fetch('/admin/settings/storage/archive-sync/status', { headers: { Accept: 'application/json' } });
        syncStatus.value = await res.json();
    } catch {
        /* átmeneti hálózati hiba — a következő tick újrapróbál */
    }
    if (syncStatus.value && syncStatus.value.running) {
        syncPoll = setTimeout(pollSync, 3000);
    } else {
        syncPoll = null;
        router.reload({ only: ['archive'] });
    }
}

onMounted(() => {
    if (syncRunning.value) pollSync();
});
onBeforeUnmount(() => {
    if (syncPoll) clearTimeout(syncPoll);
});

function diskAvailable(value) {
    return (props.archive.options.find((o) => o.value === value) || {}).available;
}

const testForm = useForm({});

function testConnection() {
    testForm.post('/admin/settings/storage/test', { preserveScroll: true });
}

// --- Cloudflare R2 ---
const r2Form = useForm({
    endpoint: props.r2.endpoint ?? '',
    access_key_id: props.r2.access_key_id ?? '',
    secret_access_key: '',
    public_bucket: props.r2.public_bucket ?? '',
    private_bucket: props.r2.private_bucket ?? '',
    import_bucket: props.r2.import_bucket ?? '',
    public_url: props.r2.public_url ?? '',
});
const r2TestForm = useForm({});

function saveR2() {
    r2Form.transform((data) => ({ ...data, _method: 'put' })).post('/admin/settings/storage/r2', {
        preserveScroll: true,
        onSuccess: () => {
            r2Form.secret_access_key = '';
        },
    });
}

function testR2() {
    r2TestForm.post('/admin/settings/storage/r2/test', { preserveScroll: true });
}

const connectionForm = useForm({
    host: props.connection.host ?? '',
    port: props.connection.port ?? '22',
    username: props.connection.username ?? '',
    root: props.connection.root ?? '/roadsidephoto',
    password: '',
    private_key: '',
    private_key_passphrase: '',
});

function saveConnection() {
    connectionForm.transform((data) => ({ ...data, _method: 'put' })).post('/admin/settings/storage', {
        preserveScroll: true,
        onSuccess: () => {
            connectionForm.password = '';
            connectionForm.private_key = '';
            connectionForm.private_key_passphrase = '';
        },
    });
}

function retry(mediaId) {
    router.post(`/admin/media/${mediaId}/retry-archive`, {}, { preserveScroll: true });
}

const typeLabel = { photo: 'Kép', video: 'Videó' };
</script>

<template>
    <Head title="Tárhely / NAS" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Tárhely — NAS archiválás</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A teljes felbontású eredeti és a megvásárolt JPEG/WebP fájlok egy háttérjob révén automatikusan átkerülnek
            a webhostingról a távoli NAS-ra (SFTP), hogy a nagy fájlok ne a webhosting tárhelyét fogyasszák. A kis
            thumbnail/vízjelezett előnézet mindig a webhostingon marad. Ez az oldal csak superadmin számára látható —
            a fotósok/feltöltők nem érik el.
        </p>

        <!-- Kezelesi emlekezteto: FTP/NAS vs. Cloudflare R2 -->
        <details class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm text-muted [&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:font-semibold [&_h3]:text-content">
            <summary class="cursor-pointer font-semibold uppercase tracking-wide text-content">
                Kezelési emlékeztető — FTP/NAS vagy Cloudflare R2
            </summary>

            <p class="mt-3">
                A nagy fájlok (feltöltött eredeti + megvásárolt letölthető JPEG/WebP/MP4) hosszú távú tárolója
                <strong>szabadon választható</strong>: maradhat a saját SFTP/NAS szerver (az eredeti terv), vagy
                a Cloudflare R2 felhőtár. A kis fájlok (thumbnail, vízjeles előnézet, HLS) is külön állíthatók.
                A kód sehol nem tartalmaz beégetett disknevet.
            </p>

            <h3>1. A választás helye — <code>.env</code></h3>
            <p>Nem itt a felületen, hanem a szerver <code>.env</code> fájljában, két sorral:</p>
            <pre class="mt-1 overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs text-content">MEDIA_PUBLIC_DISK=…    # kis fájlok: thumbnail, előnézet, HLS
MEDIA_ARCHIVE_DISK=…   # nagy fájlok: eredeti + letölthető változatok</pre>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><code>public</code> + <code>nas</code> — teljesen a saját SFTP/NAS megoldás</li>
                <li><code>public</code> + <code>local</code> — minden a webhosting lemezén, nincs külön archív réteg</li>
                <li><code>r2_public</code> + <code>r2_private</code> — teljesen Cloudflare R2</li>
                <li><code>r2_public</code> + <code>nas</code> — vegyes: kis fájlok CDN-ről, archív a NAS-on</li>
            </ul>

            <h3>2. A hitelesítő adatok helye</h3>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li><strong>NAS / SFTP</strong> (host, felhasználó, jelszó/kulcs): ezen az oldalon, lentebb — titkosítva tárolva, kapcsolat-teszt gombbal.</li>
                <li><strong>Cloudflare R2</strong> (kulcsok, bucket-nevek, publikus domain): szintén ezen az oldalon lentebb — titkosítva tárolva (a secret), vagy fallback-ként a <code>.env</code>-ből.</li>
            </ul>

            <h3>3. Váltás (bármikor, később is)</h3>
            <ol class="mt-1 list-decimal space-y-1 pl-5">
                <li>Átírod a 2 sort a <code>.env</code>-ben.</li>
                <li>Lefuttatod: <code>php artisan roadsidephoto:sync-media-storage</code> — átmásolja a meglévő fájlokat az új diskre (a forrást nem törli), és frissíti, melyik hol van.</li>
                <li><code>php artisan config:clear</code></li>
            </ol>
            <p class="mt-1">Visszaváltani ugyanígy lehet.</p>

            <h3>4. Költség — R2</h3>
            <p>
                Tárhely kb. <strong>$0,015 / GB / hó</strong> (első 10 GB ingyenes), a <strong>kimenő forgalom ingyenes</strong>
                (ez az Amazon S3-nál fizetős). Reálisan pár dollár / hó, a tárolt mennyiséggel arányosan. A saját NAS/SFTP
                szervernek is van havidíja (VPS vagy hardver) — az R2 ezt kiválthatja.
            </p>

            <h3>5. Javaslat</h3>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li>Teszteléshez: <code>public</code> + <code>local</code> (semmi külső szolgáltatás nem kell).</li>
                <li>Éles indulásnál: <code>r2_public</code> + <code>r2_private</code>, egyszeri sync.</li>
                <li>A vásárlói letöltés a <strong>kézbesítési gyorsítótár</strong> miatt mindkét esetben gyors — az archív réteg választása csak a hosszú távú tárolást érinti.</li>
            </ul>
        </details>

        <!-- Cloudflare R2 kulcsok -->
        <form class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveR2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Cloudflare R2 (S3-kompatibilis tárhely)</h2>
                <button
                    type="button"
                    :disabled="!r2Configured || r2TestForm.processing"
                    class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-40"
                    @click="testR2"
                >
                    {{ r2TestForm.processing ? 'Tesztelés…' : 'Kapcsolat tesztelése' }}
                </button>
            </div>
            <p class="mt-1 text-xs" :class="r2Configured ? 'text-accent' : 'text-muted'">
                {{ r2Configured ? 'Be van állítva.' : 'Még nincs teljesen beállítva.' }}
                <span class="text-muted">· Jelenlegi szerep: publikus disk = <code>{{ diskRoles.public }}</code>, archív = <code>{{ diskRoles.archive }}</code>, import = <code>{{ importDisk }}</code></span>
            </p>
            <p class="mt-1 text-[11px] text-muted">
                A kulcspárt a Cloudflare R2 → „Manage API Tokens" → „Create API Token" (Object Read &amp; Write) adja.
                Egy account-endpoint + egy kulcspár, 3 bucket (publikus / privát / import).
                A böngészőből közvetlen feltöltéshez az <strong class="text-content">import bucketen CORS-szabály</strong> kell
                (<code>PUT</code> engedélyezve az oldal domainjéről) — Cloudflare → R2 → bucket → Settings → CORS Policy.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">S3 API endpoint</span>
                    <input v-model="r2Form.endpoint" type="text" placeholder="https://<accountid>.r2.cloudflarestorage.com" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    <p v-if="r2Form.errors.endpoint" class="mt-1 text-xs text-accent">{{ r2Form.errors.endpoint }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Access Key ID</span>
                    <input v-model="r2Form.access_key_id" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="r2Form.errors.access_key_id" class="mt-1 text-xs text-accent">{{ r2Form.errors.access_key_id }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">
                        Secret Access Key
                        <span v-if="r2.has_secret" class="normal-case text-muted">(beállítva — üresen hagyva megmarad)</span>
                    </span>
                    <input v-model="r2Form.secret_access_key" type="password" autocomplete="new-password" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Publikus bucket</span>
                    <input v-model="r2Form.public_bucket" type="text" placeholder="roadsidephoto-public" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    <p v-if="r2Form.errors.public_bucket" class="mt-1 text-xs text-accent">{{ r2Form.errors.public_bucket }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Privát bucket</span>
                    <input v-model="r2Form.private_bucket" type="text" placeholder="roadsidephoto-private" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    <p v-if="r2Form.errors.private_bucket" class="mt-1 text-xs text-accent">{{ r2Form.errors.private_bucket }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Import bucket <span class="normal-case text-muted">(tömeges import, opcionális)</span></span>
                    <input v-model="r2Form.import_bucket" type="text" placeholder="roadsidephoto-import" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Publikus domain (R2_PUBLIC_URL)</span>
                    <input v-model="r2Form.public_url" type="text" placeholder="https://media.roadsidephoto.eu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    <p v-if="r2Form.errors.public_url" class="mt-1 text-xs text-accent">{{ r2Form.errors.public_url }}</p>
                </label>
            </div>

            <p class="mt-3 text-[11px] text-muted">
                Mentés után futtasd egyszer: <code>php artisan roadsidephoto:sync-media-storage</code> — a meglévő fájlokat átmásolja az R2-re.
                A publikus/archív disk szerepét (<code>MEDIA_PUBLIC_DISK</code> / <code>MEDIA_ARCHIVE_DISK</code> / <code>MEDIA_IMPORT_DISK</code>) továbbra is a <code>.env</code> dönti el.
            </p>

            <button type="submit" :disabled="r2Form.processing" class="mt-4 rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Mentés
            </button>
        </form>

        <!-- Archiv disk kapcsolo + NAS <-> R2 szinkron -->
        <section class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Megvásárolt fájlok — honnan töltsük le</h2>
            <p class="mt-1 text-xs text-muted">
                A teljes méretű eredeti és a megvásárolt letölthető JPEG/WebP/MP4 fájlok tárolója. Egy kapcsolóval
                átbillenthető a saját NAS/SFTP szerver és a Cloudflare R2 privát bucket között — a kód nem változik.
            </p>
            <p class="mt-1 text-xs">
                <span class="text-muted">Jelenleg: </span>
                <code class="text-accent">{{ DISK_LABEL[archive.effective] || archive.effective }}</code>
                <span class="text-muted">{{ archive.overridden ? ' (felületen beállítva)' : ' (.env alapértelmezés)' }}</span>
            </p>

            <div class="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-content">
                ⚠️ <strong>Váltás előtt futtasd le a szinkront</strong> a cél-tárolóra! A meglévő
                {{ archive.plan.media_on_archive }} archivált médiát a rendszer a váltás után az új diskon keresi —
                ha a fájlok nincsenek ott, a letöltés hibát ad.
                <span v-if="archive.plan.media_on_local > 0" class="mt-1 block text-muted">
                    ({{ archive.plan.media_on_local }} média még a webhostingon van — azok nem részei a szinkronnak,
                    előbb a háttér-archiválásnak kell lefutnia rájuk.)
                </span>
            </div>

            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="saveArchiveDisk">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Kiszolgálás innen</span>
                    <select v-model="archiveDiskForm.disk" class="rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option value="">.env szerinti alapértelmezés</option>
                        <option v-for="o in archive.options" :key="o.value" :value="o.value" :disabled="!o.available">
                            {{ o.label }}{{ o.available ? '' : ' — nincs beállítva' }}
                        </option>
                    </select>
                </label>
                <button type="submit" :disabled="archiveDiskForm.processing" class="rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Mentés
                </button>
            </form>

            <div class="mt-6 border-t border-border pt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-content">Szinkron — NAS ↔ Cloudflare R2</h3>
                <p class="mt-1 text-xs text-muted">
                    Átmásolja az összes archivált média fájljait ({{ archive.plan.media_on_archive }} média, kb.
                    {{ archive.plan.files_estimate }} fájl) a másik tárolóra. A már meglévő, azonos méretű fájlokat kihagyja,
                    így többször is futtatható. A háttérben fut (queue) — nagy adatmennyiséghez CLI:
                    <code>php artisan roadsidephoto:sync-archive-storage --from=nas --to=r2_private</code>.
                </p>

                <div class="mt-3 flex flex-wrap gap-3">
                    <button
                        type="button"
                        :disabled="syncRunning || !diskAvailable('nas') || !diskAvailable('r2_private')"
                        class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-40"
                        @click="startSync('nas', 'r2_private')"
                    >
                        NAS → R2 másolás
                    </button>
                    <button
                        type="button"
                        :disabled="syncRunning || !diskAvailable('nas') || !diskAvailable('r2_private')"
                        class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-40"
                        @click="startSync('r2_private', 'nas')"
                    >
                        R2 → NAS másolás
                    </button>
                </div>
                <p v-if="!diskAvailable('nas') || !diskAvailable('r2_private')" class="mt-2 text-[11px] text-muted">
                    A szinkronhoz MINDKÉT tároló kulcsait be kell állítani (fentebb).
                </p>

                <div v-if="syncStatus" class="mt-4 rounded-lg border border-border bg-surface-2 p-3 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-content">
                            {{ syncStatus.direction || 'Szinkron' }} —
                            <span v-if="syncStatus.running" class="text-accent">fut…</span>
                            <span v-else-if="syncStatus.cancelled" class="text-muted">megszakítva</span>
                            <span v-else class="text-accent">kész</span>
                        </span>
                        <span class="text-muted">{{ syncStatus.progress }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-1">
                        <div class="h-full rounded-full bg-accent transition-all" :style="{ width: (syncStatus.progress || 0) + '%' }"></div>
                    </div>
                    <div class="mt-2 text-muted">
                        Másolva: <strong class="text-content">{{ syncStatus.copied }}</strong> ·
                        Kihagyva: {{ syncStatus.skipped }} ·
                        Hiba: <span :class="syncStatus.failed > 0 ? 'text-accent' : ''">{{ syncStatus.failed }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapcsolati adatok szerkesztese -->
        <form class="mt-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="saveConnection">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">NAS kapcsolati adatok</h2>
                <button
                    type="button"
                    :disabled="!configured || testForm.processing"
                    class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-40"
                    @click="testConnection"
                >
                    {{ testForm.processing ? 'Tesztelés…' : 'Kapcsolat tesztelése' }}
                </button>
            </div>
            <p class="mt-1 text-xs" :class="configured ? 'text-accent' : 'text-muted'">
                {{ configured ? 'Be van állítva.' : 'Még nincs beállítva — töltsd ki alább.' }}
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Host / IP</span>
                    <input v-model="connectionForm.host" type="text" placeholder="pl. nas.pelda-domain.hu vagy 192.168.1.50" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    <p v-if="connectionForm.errors.host" class="mt-1 text-xs text-accent">{{ connectionForm.errors.host }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Port</span>
                    <input v-model="connectionForm.port" type="number" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="connectionForm.errors.port" class="mt-1 text-xs text-accent">{{ connectionForm.errors.port }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Felhasználónév</span>
                    <input v-model="connectionForm.username" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="connectionForm.errors.username" class="mt-1 text-xs text-accent">{{ connectionForm.errors.username }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Gyökér könyvtár</span>
                    <input v-model="connectionForm.root" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    <p v-if="connectionForm.errors.root" class="mt-1 text-xs text-accent">{{ connectionForm.errors.root }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">
                        Jelszó
                        <span v-if="connection.has_password" class="normal-case text-muted">(beállítva — üresen hagyva megmarad)</span>
                    </span>
                    <input v-model="connectionForm.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">
                        SSH privát kulcs
                        <span v-if="connection.has_private_key" class="normal-case text-muted">(beállítva — üresen hagyva megmarad)</span>
                    </span>
                    <textarea v-model="connectionForm.private_key" rows="3" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----…" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 font-mono text-xs text-content placeholder:text-muted focus:border-accent focus:outline-none"></textarea>
                </label>

                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">SSH kulcs jelmondat (ha van)</span>
                    <input v-model="connectionForm.private_key_passphrase" type="password" autocomplete="new-password" class="w-full max-w-xs rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
            </div>

            <p class="mt-3 text-[11px] text-muted">
                Vagy jelszót, vagy SSH privát kulcsot adj meg — amelyiket kitöltöd mentéskor, az lesz érvényben.
            </p>

            <button type="submit" :disabled="connectionForm.processing" class="mt-4 rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                Mentés
            </button>
        </form>

        <!-- KPI -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-content">{{ stats.local }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Még lokálisan (webhosting)</div>
            </div>
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <div class="text-2xl font-bold text-accent">{{ stats.nas }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">NAS-ra archiválva</div>
            </div>
            <div class="rounded-[var(--radius-base)] border p-5" :class="stats.failing > 0 ? 'border-accent bg-accent/10' : 'border-border bg-surface-1'">
                <div class="text-2xl font-bold" :class="stats.failing > 0 ? 'text-accent' : 'text-content'">{{ stats.failing }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-muted">Hibás archiválás</div>
            </div>
        </div>

        <!-- Hibalista -->
        <div v-if="failingMedia.length > 0" class="mt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Hibás archiválások</h2>
            <div class="mt-3 overflow-x-auto rounded-[var(--radius-base)] border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-1 text-[11px] uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Média</th>
                            <th class="px-4 py-3 font-semibold">Esemény</th>
                            <th class="px-4 py-3 font-semibold">Fotós</th>
                            <th class="px-4 py-3 font-semibold">Próbálkozás</th>
                            <th class="px-4 py-3 font-semibold">Hiba</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in failingMedia" :key="item.id" class="hover:bg-surface-1">
                            <td class="px-4 py-3 text-content">#{{ item.id }} · {{ typeLabel[item.type] }}</td>
                            <td class="px-4 py-3 text-muted">{{ item.event?.name }}</td>
                            <td class="px-4 py-3 text-muted">{{ item.photographer?.name }}</td>
                            <td class="px-4 py-3 text-muted">{{ item.archive_attempts }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-xs text-accent" :title="item.archive_error">{{ item.archive_error }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="retry(item.id)">
                                    Újrapróbálás
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
