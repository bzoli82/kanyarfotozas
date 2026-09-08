<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    enabled: Boolean,
    description: String,
    defaultDescription: String,
});

const form = useForm({
    enabled: props.enabled,
    description: props.description,
});

function submit() {
    form.put('/admin/settings/geo', { preserveScroll: true });
}

function resetDescription() {
    form.description = props.defaultDescription;
}
</script>

<template>
    <Head title="GEO — AI-kereshetőség" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">GEO — AI-kereshetőség</h1>

        <!-- EMLÉKEZTETŐ: mi ez, hogyan működik -->
        <div class="mt-4 max-w-3xl space-y-3 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm text-muted">
            <p>
                <strong class="text-content">Mi az a GEO?</strong>
                Generative Engine Optimization — hogy az AI-keresők (ChatGPT, Perplexity, Google AI Overview, Gemini)
                megtalálják és <em>helyesen</em> idézzék az oldalt, amikor valaki róla vagy a témájáról kérdez.
            </p>
            <p>
                <strong class="text-content">Mit csinál ez az oldal?</strong>
                A klasszikus SEO-réteg (szerver-oldali meta, JSON-LD strukturált adat, <code>sitemap.xml</code>, gyors oldalak)
                már ellátja a GEO igényeinek nagy részét — az AI-crawlerek pont ezt olvassák.
                Ez a beállítás <strong class="text-content">egy plusz fájlt</strong> ad hozzá: a
                <a href="/llms.txt" target="_blank" class="text-accent hover:text-accent-hover"><code>/llms.txt</code></a>-et.
            </p>
            <p>
                <strong class="text-content">Mi az <code>/llms.txt</code>?</strong>
                Egy feltörekvő konvenció (mint a <code>robots.txt</code>): emberi nyelven, tömören elmondja az AI-crawlereknek,
                MI ez az oldal és melyek a fontos aloldalai. A rendszer <strong class="text-content">automatikusan</strong> generálja
                az alábbi leírásból + a fő oldalakból + a GYIK kérdés-válaszaiból + az aktuális élő eseményekből.
                1 órás gyorsítótár, minden mentéskor frissül. Indulás előtti / „nem kereshető" módban (SEO beállítások) nem elérhető.
            </p>
            <p>
                <strong class="text-content">GYIK.</strong>
                Az AI-keresők szeretik a jól megfogalmazott kérdés-válasz párokat. A
                <a href="/faq" target="_blank" class="text-accent hover:text-accent-hover">GYIK oldal</a> tartalma bekerül az
                <code>/llms.txt</code>-be és külön <code>FAQPage</code> strukturált adatként is — érdemes valódi vásárlói kérdésekkel bővíteni
                (jelenleg a <code>FaqItemSeeder</code>-ből jön).
            </p>
            <p>
                <strong class="text-content">Amit NEM érdemes:</strong>
                „GEO audit", tartalomfarm az AI-rangsorért, séma minden média-oldalra (azok úgyis noindex-esek).
                Ez tranzakciós, hiperlokális termék — a vásárló Google-ből vagy a pályán kirakott QR-ből jön, nem AI-chatből.
                A GEO itt főleg a <em>fotósok</em> és <em>szervezők</em> felső-tölcséres felfedezését segíti.
            </p>
        </div>

        <form class="mt-6 max-w-2xl space-y-4" @submit.prevent="submit">
            <label class="flex items-center gap-2 text-sm text-content">
                <input v-model="form.enabled" type="checkbox" class="accent-[var(--color-accent)]" />
                Az <code>/llms.txt</code> elérhető (AI-crawlerek számára)
            </label>

            <label class="block">
                <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                    Oldal-leírás (az <code>/llms.txt</code> tetejére kerül)
                    <button type="button" class="text-accent hover:text-accent-hover" @click="resetDescription">Alapértelmezett visszaállítása</button>
                </span>
                <textarea
                    v-model="form.description"
                    rows="6"
                    class="w-full rounded-lg border border-border bg-surface-1 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                ></textarea>
                <p v-if="form.errors.description" class="mt-1 text-xs text-accent">{{ form.errors.description }}</p>
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
                <a href="/llms.txt" target="_blank" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">/llms.txt megnyitása</a>
            </div>
        </form>
    </AdminLayout>
</template>
