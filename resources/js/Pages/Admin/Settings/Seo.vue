<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    defaults: { type: Object, required: true },
    urls: { type: Object, required: true },
    ogImageMaxMb: { type: Number, default: 8 },
});

const form = useForm({
    description: props.settings.description,
    title_suffix: props.settings.title_suffix,
    search_visible: props.settings.search_visible,
    google_verification: props.settings.google_verification ?? '',
    og_image: null,
    remove_og_image: false,
});

const ogPreview = ref(props.settings.og_image_url);
const ogIsCustom = ref(props.settings.og_image_is_custom);

function pickImage(e) {
    const file = e.target.files?.[0] ?? null;
    form.og_image = file;
    form.remove_og_image = false;
    if (file) ogPreview.value = URL.createObjectURL(file);
}

function resetImage() {
    form.og_image = null;
    form.remove_og_image = true;
    ogPreview.value = null;
    ogIsCustom.value = false;
}

function save() {
    form.post('/admin/settings/seo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.og_image = null;
            form.remove_og_image = false;
        },
    });
}

const descLeft = computed(() => 320 - (form.description?.length ?? 0));
</script>

<template>
    <Head title="SEO" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">SEO beállítások</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A publikus oldalak keresőoptimalizálási alapjai. A rendszer minden oldalhoz automatikusan generál
            címet, leírást, canonical-t és közösségi (OG) előnézetet — itt az alapértékeket és a globális
            kapcsolókat állítod.
        </p>

        <form class="mt-6 max-w-2xl space-y-6" @submit.prevent="save">
            <!-- Kereshetőség -->
            <section class="rounded-[var(--radius-base)] border p-5" :class="form.search_visible ? 'border-border bg-surface-1' : 'border-red-500/50 bg-red-500/10'">
                <label class="flex items-start gap-3">
                    <input v-model="form.search_visible" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                    <span>
                        <span class="text-sm font-semibold text-content">Keresők indexelhetik az oldalt</span>
                        <span class="mt-1 block text-xs text-muted">
                            Ha kikapcsolod, minden oldal <code>noindex</code> lesz és a <code>robots.txt</code> mindent tilt.
                            Éles indulás előtt / karbantartáskor hasznos. <strong class="text-content">Élesben kapcsold be.</strong>
                        </span>
                    </span>
                </label>
            </section>

            <!-- Alap leírás -->
            <label class="block rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <span class="text-sm font-semibold text-content">Alapértelmezett meta-leírás</span>
                <span class="mt-1 block text-xs text-muted">Azokon az oldalakon jelenik meg, amelyeknek nincs saját leírásuk (főoldal, kereső). ~150–160 karakter az ideális.</span>
                <textarea v-model="form.description" rows="3" maxlength="320" class="mt-2 w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"></textarea>
                <span class="mt-1 flex justify-between text-[11px] text-muted">
                    <button type="button" class="hover:text-content" @click="form.description = defaults.description">Alapértelmezett visszaállítása</button>
                    <span :class="descLeft < 0 ? 'text-red-500' : ''">{{ descLeft }}</span>
                </span>
                <p v-if="form.errors.description" class="mt-1 text-xs text-red-500">{{ form.errors.description }}</p>
            </label>

            <!-- Cím-kiegészítés -->
            <label class="block rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <span class="text-sm font-semibold text-content">Cím-kiegészítés</span>
                <span class="mt-1 block text-xs text-muted">A böngészőfül-cím a főoldalon: „<span class="text-content">{{ settings.title_suffix ? '…' : '' }}</span>” — <code>Márkanév — {{ form.title_suffix || defaults.title_suffix }}</code>. Az aloldalakon <code>Oldal neve — Márkanév</code>.</span>
                <input v-model="form.title_suffix" type="text" maxlength="120" class="mt-2 w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                <p v-if="form.errors.title_suffix" class="mt-1 text-xs text-red-500">{{ form.errors.title_suffix }}</p>
            </label>

            <!-- OG kép -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <span class="text-sm font-semibold text-content">Közösségi megosztási kép (OG)</span>
                <span class="mt-1 block text-xs text-muted">
                    Ez jelenik meg, ha valaki a főoldalt / infó-oldalt osztja meg (Facebook, Slack, LinkedIn…).
                    Az esemény-oldalak automatikusan a saját borítóképüket használják. Ajánlott: 1200×630, max {{ ogImageMaxMb }} MB.
                </span>
                <div class="mt-3 flex flex-wrap items-start gap-4">
                    <div class="aspect-[1200/630] w-56 overflow-hidden rounded-lg border border-border bg-surface-2">
                        <img v-if="ogPreview" :src="ogPreview" alt="OG előnézet" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-[11px] text-muted">Alap kép</div>
                    </div>
                    <div class="space-y-2">
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="block text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-content" @change="pickImage" />
                        <button v-if="ogIsCustom || form.og_image" type="button" class="text-[11px] font-semibold uppercase tracking-wide text-muted hover:text-content" @click="resetImage">
                            Egyedi kép eltávolítása
                        </button>
                        <p v-if="form.errors.og_image" class="text-xs text-red-500">{{ form.errors.og_image }}</p>
                    </div>
                </div>
            </div>

            <!-- Google verifikáció -->
            <label class="block rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <span class="text-sm font-semibold text-content">Google Search Console azonosító</span>
                <span class="mt-1 block text-xs text-muted">
                    A Search Console „HTML-címke" hitelesítési módjánál kapott <code>content</code> érték
                    (a <code>google-site-verification</code> meta tartalma). Csak ezt az egy értéket illeszd be.
                </span>
                <input v-model="form.google_verification" type="text" maxlength="200" placeholder="pl. AbCdEf012…" class="mt-2 w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
            </label>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                SEO-beállítások mentése
            </button>
        </form>

        <!-- Generált fájlok -->
        <div class="mt-8 max-w-2xl rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-xs text-muted">
            <p class="text-sm font-semibold text-content">Automatikusan generált</p>
            <ul class="mt-2 space-y-1">
                <li>Sitemap: <a :href="urls.sitemap" target="_blank" class="text-accent hover:underline">{{ urls.sitemap }}</a> — az infó-oldalak + minden élő esemény, óránként frissül.</li>
                <li>Robots: <a :href="urls.robots" target="_blank" class="text-accent hover:underline">{{ urls.robots }}</a> — a privát területeket tiltja, hivatkozik a sitemap-re.</li>
                <li>Minden publikus oldal: <code>canonical</code> (query nélkül), Open Graph + Twitter Card, az eseményekhez <code>Event</code> strukturált adat.</li>
            </ul>
        </div>
    </AdminLayout>
</template>
