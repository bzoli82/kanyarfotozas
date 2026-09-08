<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    enabled: Boolean,
});

const form = useForm({
    enabled: props.enabled,
});

function submit() {
    form.put('/admin/settings/location-search', { preserveScroll: true });
}
</script>

<template>
    <Head title="Helyszín-keresés (GPS)" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Helyszín-keresés — GPS sugaras szűrés</h1>

        <div class="mt-4 max-w-3xl space-y-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm text-muted">
            <p>
                <strong class="text-content">Jelenlegi állapot:</strong>
                a GPS sugaras keresés
                <strong :class="enabled ? 'text-accent' : 'text-content'">{{ enabled ? 'BE van kapcsolva' : 'KI van kapcsolva' }}</strong>.
            </p>

            <p>
                <strong class="text-content">Mi ez a funkció?</strong>
                A látogató megadja a saját koordinátáit (vagy a böngésző helymeghatározását használja, illetve a
                térképen a „Keress itt" gombbal a látható területre keres), és a rendszer a megadott sugáron
                (1–50 km) belüli fotózásokat listázza, távolság szerint növekvő sorrendben. Ez a főoldali /
                <code>/events</code> kereső-panel „GPS" füle és a térkép-modal „Keress itt" gombja.
            </p>

            <p>
                <strong class="text-content">Miért kapcsoltuk ki?</strong>
                Ez az egyetlen olyan funkció, ami a PostgreSQL mellé a <strong class="text-content">PostGIS</strong>
                térbeli kiterjesztést is <em>futásidőben</em> igényli — az <code>EventSearch</code> a
                <code>ST_DWithin</code> és <code>ST_Distance</code> függvényeket hívja. Sok egyszerűbb / managed
                adatbázis-szolgáltatás (pl. több serverless Postgres) nem, vagy csak külön csomagban adja a
                PostGIS-t. Ha a funkció be van kapcsolva egy PostGIS nélküli adatbázison, a keresés
                <strong class="text-content">hibára fut</strong> (500-as hiba a látogatónál). A hosting-döntés
                rugalmasabbá tétele érdekében ezt alapból kikapcsoltuk.
            </p>

            <p>
                <strong class="text-content">Mi működik kikapcsolva is?</strong>
                Minden más keresés: <strong class="text-content">helyszínnév</strong> / esemény-név,
                <strong class="text-content">ország</strong>, <strong class="text-content">dátum</strong>,
                <strong class="text-content">fotós</strong>, <strong class="text-content">típus</strong> (fotó/videó) —
                ezek sima SQL-lekérdezések, nincs szükségük PostGIS-re. A
                <strong class="text-content">térkép-modal is teljesen működik</strong> (a jelölők, a szűrők, egy
                helyszínre kattintás) — csak a „tőlem X km-re" sugaras szűrés és a találatok melletti
                távolság-kiírás tűnik el.
            </p>

            <div class="rounded-[var(--radius-base)] border border-accent/40 bg-accent/5 p-3">
                <p class="font-semibold text-content">Ha vissza akarod kapcsolni — a hostingnak ezt tudnia kell:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>
                        Az éles adatbázis <strong class="text-content">PostgreSQL + PostGIS</strong> legyen, és a
                        <code>postgis</code> kiterjesztés telepítve az adatbázisban:
                        <code>CREATE EXTENSION IF NOT EXISTS postgis;</code>
                    </li>
                    <li>
                        Saját VPS-en (pl. Hetzner + Forge): <code>apt install postgresql-16-postgis-3</code> — pár perc.
                    </li>
                    <li>
                        Managed Postgres-nél ellenőrizd a szolgáltató dokumentációjában, hogy a PostGIS elérhető-e
                        (pl. a Neon és a legtöbb nagy felhő-Postgres támogatja; egyes serverless ajánlatok nem).
                    </li>
                    <li>
                        Az <code>events</code> táblán amúgy is van egy natív PostGIS térbeli index (a migráció hozza
                        létre) — a migráció lefutásához a <strong class="text-content">telepítéskor</strong> már kell a
                        PostGIS. Vagyis ha eddig lefutottak a migrációk, a PostGIS jó eséllyel megvan, és ezt a
                        kapcsolót nyugodtan visszakapcsolhatod.
                    </li>
                </ul>
                <p class="mt-2">
                    Bekapcsolás után érdemes egy éles próbát tenni a <code>/events?lat=47.5&amp;lon=19&amp;radius=25</code>
                    URL-lel — ha listát ad (nem hibát), a PostGIS rendben van.
                </p>
            </div>
        </div>

        <form class="mt-6 max-w-2xl space-y-4" @submit.prevent="submit">
            <label class="flex items-center gap-2 text-sm text-content">
                <input v-model="form.enabled" type="checkbox" class="accent-[var(--color-accent)]" />
                GPS sugaras helyszín-keresés engedélyezve (PostGIS szükséges)
            </label>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
                >
                    {{ form.processing ? 'Mentés…' : 'Mentés' }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-xs font-semibold uppercase tracking-wide text-accent">Elmentve ✓</span>
            </div>
        </form>
    </AdminLayout>
</template>
