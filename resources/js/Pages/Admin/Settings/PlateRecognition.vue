<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    confidenceRange: Object,
    providers: { type: Array, default: () => [] },
});

const form = useForm({
    enabled: props.settings.enabled,
    mode: props.settings.mode,
    min_confidence: props.settings.min_confidence,
    provider: props.settings.provider,
    api_key: '',
    clear_api_key: false,
});

const modeOptions = [
    { value: 'auto_blur', label: 'Automatikus homályosítás', hint: 'A felismert rendszámtáblát a rendszer azonnal elhomályosítja a publikus előnézeten és a letölthető fájlon is.' },
    { value: 'flag_only', label: 'Csak megjelölés', hint: 'A rendszám nem lesz homályosítva, csak megjelölve — egy admin dönt a kézi feldolgozásról.' },
];

const providerLabels = {
    platerecognizer: 'Plate Recognizer (platerecognizer.com)',
    google_vision: 'Google Cloud Vision API',
};

function save() {
    form.put('/admin/settings/plate-recognition', {
        preserveScroll: true,
        onSuccess: () => {
            form.api_key = '';
            form.clear_api_key = false;
        },
    });
}
</script>

<template>
    <Head title="Rendszámfelismerés" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Rendszámfelismerés</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Az OCR-alapú rendszámfelismerő rendszer a feltöltött fotókon megkeresi a látható rendszámtáblákat.
            <strong class="text-content">Ha a fő kapcsoló ki van kapcsolva, a képfeldolgozó pipeline teljesen kihagyja
            ezt a lépést — egyetlen külső hívás sem történik.</strong>
        </p>
        <p class="mt-2 max-w-2xl text-xs text-muted">
            Szolgáltató: Plate Recognizer — ingyenes szint havi 2500 lekéréssel
            (<a href="https://platerecognizer.com/" target="_blank" rel="noopener" class="text-accent hover:underline">platerecognizer.com</a>,
            regisztráció után a „Snapshot Cloud" API kulcs). Videónál a felismerés csak jelzés (nincs automatikus homályosítás,
            mert a rendszám mozog). A már korábban feldolgozott fotókra: <code class="text-accent">php artisan roadsidephoto:analyze-plates</code>.
        </p>

        <form class="mt-6 max-w-xl space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block text-sm font-semibold text-content">Rendszámfelismerés bekapcsolva</span>
                    <span class="mt-0.5 block text-xs text-muted">Fő kapcsoló — minden más beállítás csak bekapcsolt állapotban hat.</span>
                </span>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="form.enabled"
                    class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors"
                    :class="form.enabled ? 'bg-accent' : 'bg-surface-2 border border-border'"
                    @click="form.enabled = !form.enabled"
                >
                    <span
                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition-transform"
                        :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                    />
                </button>
            </label>

            <fieldset :disabled="!form.enabled" class="space-y-5" :class="{ 'opacity-50': !form.enabled }">
                <div>
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">OCR szolgáltató</span>
                    <select
                        v-model="form.provider"
                        class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    >
                        <option v-for="p in providers" :key="p" :value="p">{{ providerLabels[p] ?? p }}</option>
                    </select>
                </div>

                <label class="block">
                    <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <span>API kulcs / token</span>
                        <span v-if="settings.has_api_key" class="text-accent">elmentve ✓</span>
                    </span>
                    <input
                        v-model="form.api_key"
                        type="password"
                        autocomplete="off"
                        :placeholder="settings.has_api_key ? '•••••••••••••••• (hagyd üresen, ha nem változtatod)' : 'Illeszd be a szolgáltató API kulcsát'"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.api_key" class="mt-1 text-xs text-accent">{{ form.errors.api_key }}</p>
                    <p class="mt-1 text-[11px] text-muted">
                        Titkosítva tároljuk (`site_settings`), a rendszer csak a feldolgozáskor fejti vissza. A mező üresen hagyva a korábbi kulcs megmarad.
                    </p>
                    <label v-if="settings.has_api_key" class="mt-1.5 flex items-center gap-2 text-[11px] text-muted">
                        <input v-model="form.clear_api_key" type="checkbox" class="accent-[var(--color-accent)]" />
                        Meglévő kulcs törlése
                    </label>
                </label>

                <div>
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Mit tegyen felismeréskor</span>
                    <div class="space-y-2">
                        <label v-for="opt in modeOptions" :key="opt.value" class="flex cursor-pointer gap-3 rounded-lg border border-border bg-surface-2 p-3">
                            <input v-model="form.mode" type="radio" :value="opt.value" class="mt-0.5 accent-[var(--color-accent)]" />
                            <span>
                                <span class="block text-sm text-content">{{ opt.label }}</span>
                                <span class="mt-0.5 block text-xs text-muted">{{ opt.hint }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <span>Minimális megbízhatóság</span>
                        <span class="text-content">{{ form.min_confidence }}%</span>
                    </span>
                    <input
                        v-model.number="form.min_confidence"
                        type="range"
                        :min="confidenceRange.min"
                        :max="confidenceRange.max"
                        class="w-full accent-[var(--color-accent)]"
                    />
                    <p class="mt-1 text-[11px] text-muted">
                        Ez alatt a felismerést a rendszer bizonytalannak tekinti és figyelmen kívül hagyja. Magasabb érték kevesebb téves találat, de több kihagyott rendszám.
                    </p>
                </label>
            </fieldset>

            <div class="pt-1">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Mentés
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
