<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    isProduction: { type: Boolean, default: false },
    source: { type: Object, default: () => ({ enabled: false, token: null, last_pull: null }) },
    target: { type: Object, default: null },
});

// --- Forrás (a titkos kulcs ki/be kapcsolása) ---
const sourceForm = useForm({ enabled: props.source.enabled, regenerate: false });
const tokenShown = ref(false);

function toggleSource(regenerate = false) {
    sourceForm.regenerate = regenerate;
    sourceForm.enabled = regenerate ? true : !props.source.enabled;
    sourceForm.put('/admin/settings/data-sync/source', { preserveScroll: true });
}

function copyToken() {
    if (props.source.token) navigator.clipboard?.writeText(props.source.token);
}

// --- Kapcsolat (helyi gép → éles) ---
const remoteForm = useForm({
    url: props.target?.remote_url ?? '',
    token: '',
});
const testForm = useForm({});
const forgetForm = useForm({});

function saveRemote() {
    remoteForm.put('/admin/settings/data-sync/remote', { preserveScroll: true });
}
function testRemote() {
    testForm.post('/admin/settings/data-sync/test', { preserveScroll: true });
}
function forgetRemote() {
    if (confirm('Biztosan törlöd az éles kapcsolatot erről a gépről?')) {
        forgetForm.delete('/admin/settings/data-sync/remote', { preserveScroll: true });
    }
}

// --- Adatbázis letöltése ---
const dbForm = useForm({ confirm: '', scrub: true });
const dbConfirmOk = computed(() => ['LETÖLTÖM', 'LETOLTOM'].includes(dbForm.confirm.trim().toUpperCase()));

function pullDatabase() {
    dbForm
        .transform((d) => ({ ...d, confirm: d.confirm.trim().toUpperCase() }))
        .post('/admin/settings/data-sync/pull-database', {
            preserveScroll: true,
            onSuccess: () => dbForm.reset('confirm'),
        });
}

// --- Média letöltése ---
const mediaForm = useForm({ with_originals: false });
function pullMedia() {
    mediaForm.post('/admin/settings/data-sync/pull-media', { preserveScroll: true });
}
</script>

<template>
    <Head title="Éles ↔ helyi szinkron" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Éles ↔ helyi szinkron</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            Az éles szerver adatait (adatbázis, rendelések, üzenetek, események, média) le lehet tölteni
            a fejlesztői gépedre — mintha exportálnád élesről és importálnád helyben.
            <strong class="text-content">A szinkron mindig egyirányú: éles → helyi.</strong>
            Élesítés (első valódi rendelés) után visszafelé SOHA ne használd.
        </p>

        <details class="mt-4 max-w-2xl rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm">
            <summary class="cursor-pointer font-semibold text-content">
                Hogyan tartsd szinkronban az élest és a fejlesztői verziót? (rövid magyarázat)
            </summary>
            <div class="mt-3 space-y-4 text-muted">
                <p>
                    <strong class="text-content">Az alap-szabály:</strong> a <strong class="text-content">kód</strong> a fejlesztői
                    géptől megy az élesre (<code>git push</code> → automatikus deploy), az <strong class="text-content">adat</strong>
                    pedig az élestől a fejlesztői gépre (ez az oldal). A kettőt soha ne keverd: élesen ne szerkessz kódot, és
                    ne futtass <code>migrate:fresh</code>-t vagy demó-seedert — az kitörölné a valódi adatot.
                </p>

                <div>
                    <p class="font-semibold text-content">Amikor eszedbe jut egy új funkció, ezt a kört csináld:</p>
                    <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                        <li>
                            <strong class="text-content">Húzd le az éles adatot ide</strong> (2. lépés lentebb, a „Biztonságos
                            másolat" pipa maradjon bekapcsolva). Innentől a fejlesztői géped úgy néz ki, mint az éles — csak
                            teszt-kulcsokkal.
                        </li>
                        <li>
                            <strong class="text-content">Fejleszd a funkciót</strong> a fejlesztői gépen egy külön git-ágon.
                            Ha új adatbázis-mező kell, csak <em>hozzáadó</em> migrációt írj (nullable oszlop vagy default érték) —
                            ez éles, feltöltött táblán fog lefutni.
                        </li>
                        <li><strong class="text-content">Teszteld helyben</strong> a lehúzott éles adaton.</li>
                        <li>
                            <strong class="text-content">Push a <code>main</code> ágra.</strong> A deploy automatikusan lefut:
                            friss kód + assetek, és <code>php artisan migrate</code> (csak az új migrációk — az éles adat marad).
                        </li>
                    </ol>
                </div>

                <div>
                    <p class="font-semibold text-content">Mire figyelj:</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5">
                        <li>A titkos kulcsok (Stripe / SMTP / R2) élesen és helyben <strong class="text-content">külön</strong> vannak.
                            A „Biztonságos másolat" letöltés után helyben újra be kell írnod a <em>teszt</em> kulcsokat a Kritikus
                            beállításoknál — az éles kulcsokat ez sosem érinti.</li>
                        <li>Élesen a valódi seedereket (szerepkörök, alap-beállítások, GY.I.K.) csak <strong class="text-content">egyszer</strong>,
                            induláskor futtatod.</li>
                        <li>A GY.I.K. tartalma seederből jön: ha módosítod, a <code>FaqItemSeeder</code>-t szerkeszd, pushold, és
                            élesen futtass egy <code>php artisan db:seed --class=FaqItemSeeder</code>-t.</li>
                        <li>Napi automatikus adatbázis-mentés már be van építve — csak működő ütemező (cron / Coolify feladat) kell hozzá.</li>
                    </ul>
                </div>
            </div>
        </details>

        <!-- ================= ÉLES PÉLDÁNY: forrás-kulcs ================= -->
        <div v-if="isProduction" class="mt-6 max-w-2xl space-y-4">
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Ez a példány az éles forrás</h2>
                <p class="mt-1 text-xs text-muted">
                    Kapcsold be, és másold a titkos kulcsot a <strong class="text-content">helyi géped</strong>
                    ugyanezen oldalára. Ezzel a kulccsal a helyi géped le tudja tölteni az éles adatbázis egy másolatát.
                </p>

                <div class="mt-4 flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-xs font-semibold uppercase tracking-wide"
                        :class="source.enabled ? 'bg-surface-2 text-muted hover:text-content' : 'bg-accent text-white hover:bg-accent-hover'"
                        :disabled="sourceForm.processing"
                        @click="toggleSource(false)"
                    >
                        {{ source.enabled ? 'Kikapcsolás' : 'Bekapcsolás' }}
                    </button>
                    <span v-if="source.enabled" class="text-xs font-semibold uppercase tracking-wide text-emerald-500">● Bekapcsolva</span>
                    <span v-else class="text-xs font-semibold uppercase tracking-wide text-muted">○ Kikapcsolva</span>
                </div>

                <div v-if="source.enabled && source.token" class="mt-4 space-y-2">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted">Titkos kulcs</span>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 truncate rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs text-content">
                            {{ tokenShown ? source.token : '•'.repeat(48) }}
                        </code>
                        <button type="button" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="tokenShown = !tokenShown">
                            {{ tokenShown ? 'Elrejt' : 'Mutat' }}
                        </button>
                        <button type="button" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="copyToken">Másol</button>
                    </div>
                    <button type="button" class="text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-accent" :disabled="sourceForm.processing" @click="toggleSource(true)">
                        Új kulcs generálása (a régi azonnal érvénytelen lesz)
                    </button>
                </div>

                <div class="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-content">
                    ⚠️ Ezzel a kulccsal bárki, aki ismeri, letöltheti az éles adatbázis egy másolatát —
                    <strong>vásárlói e-mailekkel, rendelésekkel együtt</strong>. Csak a saját, megbízható gépeden add meg.
                    Ha kiszivárgott: generálj újat. Használat után nyugodtan kapcsold ki.
                </div>

                <p v-if="source.last_pull" class="mt-3 text-[11px] text-muted">
                    Utolsó letöltés: {{ new Date(source.last_pull.at).toLocaleString('hu-HU') }} ({{ source.last_pull.ip }})
                </p>
            </div>
        </div>

        <!-- ================= HELYI PÉLDÁNY: letöltés élesről ================= -->
        <div v-else class="mt-6 max-w-2xl space-y-6">
            <!-- Kapcsolat -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">1. Kapcsolat az éles szerverhez</h2>
                <p class="mt-1 text-xs text-muted">
                    Add meg az éles oldal címét és a titkos kulcsot (az éles admin ugyanezen oldaláról, „Ez a példány az éles forrás").
                    A kulcs titkosítva, csak ezen a gépen tárolódik.
                </p>

                <div v-if="target?.has_remote" class="mt-3 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs text-content">
                    ● Kapcsolat beállítva: <strong>{{ target.remote_url }}</strong>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Éles URL</span>
                        <input v-model="remoteForm.url" type="url" placeholder="https://pelda.hu vagy https://xxxx.sslip.io" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="remoteForm.errors.url" class="mt-1 text-xs text-accent">{{ remoteForm.errors.url }}</p>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">
                            Titkos kulcs {{ target?.has_remote ? '(hagyd üresen, ha nem változott — akkor a „Kapcsolat tesztelése" gombot használd)' : '' }}
                        </span>
                        <input v-model="remoteForm.token" type="password" autocomplete="off" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="remoteForm.errors.token" class="mt-1 text-xs text-accent">{{ remoteForm.errors.token }}</p>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" :disabled="remoteForm.processing" class="rounded-lg bg-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60" @click="saveRemote">
                        {{ remoteForm.processing ? 'Mentés…' : 'Mentés + kapcsolat tesztelése' }}
                    </button>
                    <button v-if="target?.has_remote" type="button" :disabled="testForm.processing" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-60" @click="testRemote">
                        {{ testForm.processing ? 'Tesztelés…' : 'Kapcsolat tesztelése' }}
                    </button>
                    <button v-if="target?.has_remote" type="button" :disabled="forgetForm.processing" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-accent" @click="forgetRemote">
                        Kapcsolat törlése
                    </button>
                </div>
            </div>

            <!-- Adatbázis -->
            <div class="rounded-[var(--radius-base)] border-2 border-accent/50 bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">2. Éles adatbázis letöltése és visszaállítása</h2>

                <div class="mt-3 space-y-2">
                    <div class="rounded-lg border border-accent/50 bg-accent/10 p-3 text-xs text-content">
                        ⚠️ <strong>Ez TÖRLI a jelenlegi helyi adatbázisod</strong> (a helyi „<strong>{{ target?.db_name }}</strong>"),
                        és lecseréli az éles szerver másolatával. Minden helyi tesztadatod és kísérleted elvész. Nem visszavonható.
                    </div>
                    <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-content">
                        ⚠️ <strong>Csak éles → helyi irányban használd.</strong> Élesítés (első valódi rendelés) után soha ne
                        töltsd vissza a helyi adatokat az élesre — az felülírná a valódi rendeléseket.
                    </div>
                </div>

                <label class="mt-4 flex gap-2.5 text-xs text-content">
                    <input v-model="dbForm.scrub" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                    <span>
                        <strong>Biztonságos másolat készítése</strong> (ajánlott): a fizetési/API-kulcsok, az SMTP-jelszó és a
                        2FA-titkok törlése + a vásárlói e-mail-címek anonimizálása. Így a helyi másolat fejlesztésre biztonságos.
                        Kapcsold ki csak akkor, ha pontos 1:1 másolatra van szükséged (és a helyi <code>APP_KEY</code> megegyezik az élessel).
                    </span>
                </label>

                <label class="mt-4 block max-w-[16rem]">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Megerősítés — gépeld be: LETÖLTÖM</span>
                    <input v-model="dbForm.confirm" type="text" autocomplete="off" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                </label>
                <p v-if="dbForm.errors.confirm" class="mt-1 text-xs text-accent">{{ dbForm.errors.confirm }}</p>

                <button
                    type="button"
                    :disabled="dbForm.processing || !dbConfirmOk || !target?.has_remote"
                    class="mt-4 rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
                    @click="pullDatabase"
                >
                    {{ dbForm.processing ? 'Letöltés és visszaállítás folyamatban… (ne zárd be)' : 'Éles adatbázis letöltése és visszaállítása' }}
                </button>
                <p v-if="!target?.has_remote" class="mt-2 text-[11px] text-muted">Előbb állítsd be a kapcsolatot (1. lépés).</p>
            </div>

            <!-- Média -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">3. Média (képek, előnézetek, videók)</h2>

                <div v-if="target?.media_uses_shared_r2" class="mt-3 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-xs text-content">
                    ✅ A helyi géped ugyanazt az éles R2 tárat használja — a média <strong>automatikusan szinkronban van</strong>,
                    nincs mit letölteni. (A 2. lépés után a letöltött rendelések képei egyből látszanak.)
                </div>

                <template v-else>
                    <div class="mt-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-content">
                        ⚠️ A helyi géped saját (lokális) tárat használ. A letöltött adatbázis képei nem fognak megjelenni,
                        amíg le nem másolod a fájlokat az éles R2-ből. Alternatíva: a
                        <strong>Tárhely → „Cloudflare R2"</strong> szekcióban állítsd a helyi tárat az élesre (akkor ez a lépés kimarad).
                    </div>

                    <div v-if="!target?.media_r2_configured" class="mt-3 rounded-lg border border-border bg-surface-2 p-3 text-xs text-muted">
                        A másoláshoz előbb add meg az R2 kulcsokat a <strong>Tárhely → „Cloudflare R2"</strong> szekcióban
                        (ugyanazokat, mint élesen). Utána ez a gomb aktív lesz.
                    </div>

                    <label class="mt-4 flex gap-2.5 text-xs text-content">
                        <input v-model="mediaForm.with_originals" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                        <span>Az eredeti, teljes felbontású fájlokkal együtt (jóval nagyobb — általában csak a letölthető termékhez kell).</span>
                    </label>

                    <div class="mt-3 rounded-lg border border-border bg-surface-2 p-3 text-xs text-muted">
                        A másolás sok GB is lehet, és eltarthat egy ideig — <strong>hagyd nyitva a böngészőt</strong>. A már meglévő
                        (azonos méretű) fájlokat kihagyja, ezért ha megszakad, csak kattints újra a folytatáshoz.
                    </div>

                    <button
                        type="button"
                        :disabled="mediaForm.processing || !target?.media_r2_configured"
                        class="mt-4 rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
                        @click="pullMedia"
                    >
                        {{ mediaForm.processing ? 'Másolás folyamatban… (ne zárd be)' : 'Média letöltése az R2-ről' }}
                    </button>
                </template>
            </div>

            <!-- Forráskulcs a helyi gépen (haladó) -->
            <details class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-content">
                    Forráskulcs — ha ezt a gépet használnád forrásként (haladó)
                </summary>
                <p class="mt-2 text-xs text-muted">
                    Általában nincs rá szükség — ezt az éles szerveren kapcsolod be. A titkos kulcs a
                    <code>site_settings</code>-ben van, ezért a 2. lépéssel átjön az éles kulcs is (majd cseréld le).
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <button type="button" class="rounded-lg px-4 py-2 text-xs font-semibold uppercase tracking-wide" :class="source.enabled ? 'bg-surface-2 text-muted hover:text-content' : 'bg-accent text-white hover:bg-accent-hover'" :disabled="sourceForm.processing" @click="toggleSource(false)">
                        {{ source.enabled ? 'Kikapcsolás' : 'Bekapcsolás' }}
                    </button>
                    <span class="text-xs font-semibold uppercase tracking-wide" :class="source.enabled ? 'text-emerald-500' : 'text-muted'">
                        {{ source.enabled ? '● Bekapcsolva' : '○ Kikapcsolva' }}
                    </span>
                </div>
                <div v-if="source.enabled && source.token" class="mt-3 flex items-center gap-2">
                    <code class="flex-1 truncate rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs text-content">{{ tokenShown ? source.token : '•'.repeat(48) }}</code>
                    <button type="button" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="tokenShown = !tokenShown">{{ tokenShown ? 'Elrejt' : 'Mutat' }}</button>
                    <button type="button" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="copyToken">Másol</button>
                </div>
            </details>
        </div>
    </AdminLayout>
</template>
