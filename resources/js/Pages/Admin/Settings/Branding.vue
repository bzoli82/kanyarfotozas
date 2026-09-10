<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const props = defineProps({
    branding: Object,
    watermarkPreview: String,
});

const { mediaUrl } = useMediaUrl();

const form = useForm({
    name: props.branding.name,
    logo_lead: props.branding.logo_lead,
    logo_tail: props.branding.logo_tail,
    logo: null,
    logo_dark: null,
    remove_logo: false,
    remove_logo_dark: false,
});

const watermarkLive = computed(() => (form.name || '').toUpperCase());

const logoPreview = ref(null);
const logoDarkPreview = ref(null);

function pick(field, previewRef, event) {
    const file = event.target.files?.[0] ?? null;
    form[field] = file;
    form[field === 'logo' ? 'remove_logo' : 'remove_logo_dark'] = false;
    previewRef.value = file ? URL.createObjectURL(file) : null;
}

function clearSlot(field) {
    form[field] = null;
    form[field === 'logo' ? 'remove_logo' : 'remove_logo_dark'] = true;
    if (field === 'logo') logoPreview.value = null;
    else logoDarkPreview.value = null;
}

const currentLogo = computed(() => (props.branding.logo ? mediaUrl(props.branding.logo) : null));
const currentLogoDark = computed(() => (props.branding.logo_dark ? mediaUrl(props.branding.logo_dark) : null));

function save() {
    // Fájlfeltöltés + PUT route → Inertia _method-spoofing (multipart POST).
    form.transform((data) => ({ ...data, _method: 'put' })).post('/admin/settings/branding', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            form.logo_dark = null;
            form.remove_logo = false;
            form.remove_logo_dark = false;
            logoPreview.value = null;
            logoDarkPreview.value = null;
        },
    });
}
</script>

<template>
    <Head title="Márkajel" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Oldal neve / márkajel</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            A fejlécben vagy a feltöltött <strong class="text-content">logó</strong>, vagy — ha nincs kép — a
            kétszínű szöveg jelenik meg. A név az oldalcímekbe, az e-mailekbe és a
            <strong class="text-content">vízjelbe</strong> is bekerül.
        </p>

        <form class="mt-6 max-w-2xl space-y-6 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Oldal neve</span>
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="60"
                    class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-accent">{{ form.errors.name }}</p>
            </label>

            <!-- Feltöltött logó -->
            <div class="space-y-3 rounded-[var(--radius-base)] border border-border bg-surface-2 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Logó (kép)</p>
                <p class="text-[11px] leading-relaxed text-muted">
                    Ajánlott: vízszintes, <strong class="text-content">kb. 4:1 képarány</strong> (pl. SVG, vagy PNG/WebP
                    átlátszó háttérrel, min. 600×150 px). A fejlécben kb. 28 px magasan jelenik meg.
                    SVG a legjobb (éles minden méretben). Max 1 MB.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <span class="mb-1 block text-[11px] font-medium text-content">Fő logó (világos háttérre)</span>
                        <div class="mb-2 flex h-16 items-center justify-center rounded border border-border bg-white p-2">
                            <img v-if="logoPreview || (currentLogo && !form.remove_logo)" :src="logoPreview || currentLogo" alt="" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-[10px] text-muted">nincs</span>
                        </div>
                        <input type="file" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp" class="block w-full text-[11px] text-muted file:mr-2 file:rounded file:border-0 file:bg-surface-1 file:px-2 file:py-1 file:text-[11px] file:text-content" @change="pick('logo', logoPreview, $event)" />
                        <button v-if="currentLogo && !form.remove_logo && !logoPreview" type="button" class="mt-1 text-[11px] text-accent hover:underline" @click="clearSlot('logo')">Logó törlése</button>
                        <p v-if="form.errors.logo" class="mt-1 text-xs text-accent">{{ form.errors.logo }}</p>
                    </div>

                    <div>
                        <span class="mb-1 block text-[11px] font-medium text-content">Logó sötét háttérre <span class="text-muted">(opcionális)</span></span>
                        <div class="mb-2 flex h-16 items-center justify-center rounded border border-border bg-neutral-900 p-2">
                            <img v-if="logoDarkPreview || (currentLogoDark && !form.remove_logo_dark)" :src="logoDarkPreview || currentLogoDark" alt="" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-[10px] text-neutral-500">a fő logó</span>
                        </div>
                        <input type="file" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp" class="block w-full text-[11px] text-muted file:mr-2 file:rounded file:border-0 file:bg-surface-1 file:px-2 file:py-1 file:text-[11px] file:text-content" @change="pick('logo_dark', logoDarkPreview, $event)" />
                        <button v-if="currentLogoDark && !form.remove_logo_dark && !logoDarkPreview" type="button" class="mt-1 text-[11px] text-accent hover:underline" @click="clearSlot('logo_dark')">Törlés</button>
                        <p v-if="form.errors.logo_dark" class="mt-1 text-xs text-accent">{{ form.errors.logo_dark }}</p>
                    </div>
                </div>
                <p class="text-[11px] text-muted">
                    A hero-fejléc mindig sötét — arra a „sötét háttérre" logó (vagy annak hiányában a fő logó) kerül.
                    A többi helyen (lábléc, admin, világos téma) a témához illő változat.
                </p>
            </div>

            <!-- Szöveges logó (ha nincs kép) -->
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szöveges logó — alap rész</span>
                    <input
                        v-model="form.logo_lead"
                        type="text"
                        maxlength="30"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p v-if="form.errors.logo_lead" class="mt-1 text-xs text-accent">{{ form.errors.logo_lead }}</p>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Szöveges logó — akcent rész</span>
                    <input
                        v-model="form.logo_tail"
                        type="text"
                        maxlength="30"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                    />
                    <p class="mt-1 text-[11px] text-muted">Csak akkor látszik, ha nincs feltöltött logó.</p>
                </label>
            </div>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-2 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Előnézet (szöveges)</p>
                <p class="font-display mt-2 text-lg font-bold tracking-tight text-content">
                    {{ form.logo_lead }}<span class="text-accent">{{ form.logo_tail }}</span>
                </p>
                <p class="mt-3 text-[11px] text-muted">Vízjel szövege: <span class="font-semibold text-content">{{ watermarkLive }}</span></p>
            </div>

            <div class="pt-1">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    Mentés
                </button>
                <span class="ml-3 text-[11px] text-muted">A vízjel új felvételeknél lép életbe; a régiek újrafeldolgozással frissülnek.</span>
            </div>
        </form>
    </AdminLayout>
</template>
