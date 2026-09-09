<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    modes: Array,
    fonts: Array,
    paletteKeys: Array,
    animation: Object,
    animationPageModes: Array,
    animationHeroModes: Array,
    animationCardModes: Array,
});

const form = useForm({
    preset: props.settings.preset,
    mode: props.settings.mode,
    accent_color: props.settings.accent_color,
    border_radius: props.settings.border_radius,
    font_family: props.settings.font_family,
    custom: {
        light: { ...props.settings.custom.light },
        dark: { ...props.settings.custom.dark },
    },
    anim_enabled: props.animation.enabled,
    anim_preset: props.animation.preset,
    anim_page: props.animation.page,
    anim_hero: props.animation.hero,
    anim_cards: props.animation.cards,
    anim_reveal: props.animation.reveal,
    anim_counters: props.animation.counters,
});

// Animáció-előnézet: a kiválasztott (még nem mentett) stílus numerikus értékei.
const animPreset = computed(
    () => props.animation.presetOptions.find((o) => o.value === form.anim_preset) ?? props.animation.presetOptions[1],
);
const demoKey = ref(0);
function replayDemo() {
    demoKey.value++;
}

// A kártya-hover előnézet 3D-dőlése (a v-tilt direktíva a MENTETT értéket nézi,
// itt a még nem mentett választásra kell reagálni).
const previewCard = ref(null);
function previewTilt(e) {
    if (form.anim_cards !== 'tilt' || !previewCard.value) return;
    const r = previewCard.value.getBoundingClientRect();
    previewCard.value.style.setProperty('--tx', `${(((e.clientX - r.left) / r.width - 0.5) * 6).toFixed(2)}deg`);
    previewCard.value.style.setProperty('--ty', `${((-((e.clientY - r.top) / r.height - 0.5)) * 6).toFixed(2)}deg`);
}
function previewTiltReset() {
    previewCard.value?.style.setProperty('--tx', '0deg');
    previewCard.value?.style.setProperty('--ty', '0deg');
}

const presetByKey = (key) => props.settings.presets.find((p) => p.key === key);

// A kivalasztott sema tenyleges palettaja (a `custom` a form szerkesztett szineibol).
const activePalette = computed(() => {
    if (form.preset === 'custom') return form.custom;
    const p = presetByKey(form.preset);
    return { light: p.light, dark: p.dark };
});

function pickPreset(key) {
    form.preset = key;
    if (key !== 'custom') {
        const p = presetByKey(key);
        form.accent_color = p.accent;
        // A custom szerkesztot is feltoltjuk a valasztott semaval, hogy onnan lehessen finomhangolni.
        form.custom = { light: { ...p.light }, dark: { ...p.dark } };
    }
}

function save() {
    // A séma színeit az app.blade.php <head>-jébe injektált <style> adja, ami csak
    // teljes oldalbetöltéskor frissül — ezért mentés után újratöltünk, hogy a
    // változás azonnal látszódjon (nem csak a következő hard refresh után).
    form.put('/admin/settings/theme', {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
}

function resetOwnPreference() {
    try {
        localStorage.removeItem('roadsidephoto.theme');
    } catch (e) { /* localStorage nem elerheto */ }
    window.location.reload();
}

// Egy mini oldal-mockup stilusa a paletta + akcent + sarok + font alapjan.
function previewVars(palette) {
    return {
        '--p-bg': palette.surface_0,
        '--p-card': palette.surface_1,
        '--p-hi': palette.surface_2,
        '--p-border': palette.border,
        '--p-text': palette.content,
        '--p-muted': palette.muted,
        '--p-accent': form.accent_color,
        '--p-radius': `${form.border_radius}px`,
        fontFamily: form.font_family,
    };
}
</script>

<template>
    <Head title="Téma beállítások" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Téma beállítások</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Válassz egy színsémát (mindegyik világos + sötét párban jön), vagy állítsd össze a sajátodat.
            A séma minden nyilvános és admin oldalra hat. A látogató a fejléc világos/sötét/rendszer gombjával
            a saját módját külön is beállíthatja — a séma színei mindkét módban érvényesek.
        </p>

        <!-- Séma-választó -->
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <button
                v-for="p in settings.presets"
                :key="p.key"
                type="button"
                class="rounded-[var(--radius-base)] border p-3 text-left transition-colors"
                :class="form.preset === p.key ? 'border-accent ring-1 ring-accent' : 'border-border hover:border-accent/50'"
                @click="pickPreset(p.key)"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-content">{{ p.label }}</span>
                    <span v-if="p.key === 'default'" class="rounded-full border border-border px-1.5 py-0.5 text-[9px] uppercase tracking-wide text-muted">alap</span>
                </div>
                <div class="mt-2 flex gap-1.5">
                    <span
                        v-for="mode in ['light', 'dark']"
                        :key="mode"
                        class="flex h-10 flex-1 items-center gap-1 rounded-md border px-1.5"
                        :style="{ background: p[mode].surface_0, borderColor: p[mode].border }"
                    >
                        <span class="h-4 w-4 rounded" :style="{ background: p[mode].surface_1 }" />
                        <span class="h-4 w-4 rounded" :style="{ background: p[mode].surface_2 }" />
                        <span class="h-4 w-4 rounded" :style="{ background: p.accent }" />
                    </span>
                </div>
            </button>

            <button
                type="button"
                class="rounded-[var(--radius-base)] border p-3 text-left transition-colors"
                :class="form.preset === 'custom' ? 'border-accent ring-1 ring-accent' : 'border-border hover:border-accent/50'"
                @click="pickPreset('custom')"
            >
                <span class="text-xs font-semibold text-content">Testreszabott</span>
                <div class="mt-2 flex h-10 items-center justify-center gap-1.5 rounded-md border border-dashed border-border text-[11px] text-muted">
                    minden szín kézzel
                </div>
            </button>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <form class="space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
                <!-- Custom paletta-szerkeszto -->
                <div v-if="form.preset === 'custom'" class="space-y-3">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted">Testreszabott paletta</span>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-muted">Világos</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-muted">Sötét</span>
                        <template v-for="k in paletteKeys" :key="k.key">
                            <label class="flex items-center gap-2">
                                <input v-model="form.custom.light[k.key]" type="color" class="h-7 w-9 shrink-0 cursor-pointer rounded border border-border bg-surface-2 p-0.5" />
                                <span class="truncate text-[11px] text-muted">{{ k.label }}</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.custom.dark[k.key]" type="color" class="h-7 w-9 shrink-0 cursor-pointer rounded border border-border bg-surface-2 p-0.5" />
                                <span class="truncate text-[11px] text-muted">{{ k.label }}</span>
                            </label>
                        </template>
                    </div>
                    <p v-if="form.errors['custom.light.surface_0'] || form.errors['custom.dark.surface_0']" class="text-xs text-accent">
                        Minden szín #rrggbb formátumú kell legyen.
                    </p>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Alapértelmezett megjelenítési mód</span>
                    <select v-model="form.mode" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option v-for="m in modes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </label>

                <div class="rounded-lg border border-accent/30 bg-accent/5 p-3 text-xs text-muted">
                    <p>
                        <strong class="text-content">Fontos:</strong> ha egy látogató (te is) valaha rákattintott a fejlécben lévő
                        világos/sötét/rendszer gombra, az a választás elmentődik az ő böngészőjében, és onnantól felülírja
                        ezt az alapértelmezett módot — a mentés utáni váltás nála csak akkor látszik, ha maga is újra vált, vagy törli a mentett választását.
                    </p>
                    <button type="button" class="mt-2 font-semibold uppercase tracking-wide text-accent hover:text-accent-hover" @click="resetOwnPreference">
                        Saját mentett beállításom törlése ebben a böngészőben
                    </button>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Akcentszín (gombok, linkek, kiemelések)</span>
                    <div class="flex items-center gap-3">
                        <input v-model="form.accent_color" type="color" class="h-10 w-14 shrink-0 cursor-pointer rounded-lg border border-border bg-surface-2 p-1" />
                        <input v-model="form.accent_color" type="text" maxlength="7" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                    </div>
                    <p v-if="form.errors.accent_color" class="mt-1 text-xs text-accent">{{ form.errors.accent_color }}</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted">
                        <span>Sarok-lekerekítés (kártyák, gombok)</span>
                        <span class="text-content">{{ form.border_radius }} px</span>
                    </span>
                    <input v-model.number="form.border_radius" type="range" min="0" max="32" class="w-full accent-[var(--color-accent)]" />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Betűtípus</span>
                    <select v-model="form.font_family" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none">
                        <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                </label>

                <!-- Animációk -->
                <div class="rounded-lg border border-border">
                    <div class="flex items-center justify-between border-b border-border px-3 py-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Animációk</span>
                        <label class="flex items-center gap-2 text-[11px] text-content">
                            <input v-model="form.anim_enabled" type="checkbox" class="accent-[var(--color-accent)]" />
                            Bekapcsolva
                        </label>
                    </div>
                    <div class="space-y-3 px-3 py-3" :class="{ 'pointer-events-none opacity-40': !form.anim_enabled }">
                        <p class="text-[11px] text-muted">
                            A <code>prefers-reduced-motion</code> beállítású látogatóknál minden animáció automatikusan kikapcsol.
                        </p>

                        <div>
                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Stílus</span>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="o in animation.presetOptions"
                                    :key="o.value"
                                    type="button"
                                    class="flex-1 rounded-md border px-2 py-1.5 text-[11px] transition-colors"
                                    :class="form.anim_preset === o.value ? 'border-accent text-accent' : 'border-border text-muted hover:border-accent/50'"
                                    @click="form.anim_preset = o.value; replayDemo()"
                                >
                                    {{ o.label }}
                                </button>
                            </div>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Oldalváltás</span>
                            <select v-model="form.anim_page" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                                <option v-for="o in animationPageModes" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Főoldali hero</span>
                            <select v-model="form.anim_hero" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                                <option v-for="o in animationHeroModes" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </label>

                        <div>
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Galéria- / kereső-kártyák (hover)</span>
                                <select v-model="form.anim_cards" class="w-full appearance-none rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                                    <option v-for="o in animationCardModes" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>
                            <!-- Élő kártya-előnézet: vidd rá a kurzort -->
                            <div :data-anim-cards="form.anim_cards" class="mt-2">
                                <div
                                    ref="previewCard"
                                    class="hover-card mx-auto w-40 overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-2"
                                    @mousemove="previewTilt"
                                    @mouseleave="previewTiltReset"
                                >
                                    <div class="aspect-[4/3] overflow-hidden">
                                        <div class="hover-card__media h-full w-full" :style="{ background: `linear-gradient(135deg, ${form.accent_color}, var(--color-surface-1))` }"></div>
                                    </div>
                                    <div class="p-2 text-[10px] text-muted">Próba kártya — hover</div>
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-xs text-content">
                            <input v-model="form.anim_reveal" type="checkbox" class="accent-[var(--color-accent)]" />
                            Görgetéses megjelenés (a blokkok beúsznak, ahogy a képernyőre érnek)
                        </label>
                        <label class="flex items-center gap-2 text-xs text-content">
                            <input v-model="form.anim_counters" type="checkbox" class="accent-[var(--color-accent)]" />
                            Statisztika-számlálók (0-ról felszámolnak)
                        </label>

                        <div class="rounded-md border border-border bg-surface-2 p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-muted">Előnézet</span>
                                <button type="button" class="text-[11px] font-semibold text-accent hover:text-accent-hover" @click="replayDemo">▸ Lejátszás</button>
                            </div>
                            <div
                                :key="demoKey"
                                class="mt-2 flex gap-2"
                                :style="{ '--d': animPreset.duration + 'ms', '--y': animPreset.distance + 'px', '--s': animPreset.stagger + 'ms' }"
                            >
                                <span
                                    v-for="n in 3"
                                    :key="n"
                                    class="anim-demo-bar h-8 flex-1 rounded"
                                    :style="{ background: form.accent_color, animationDelay: `calc(${n - 1} * var(--s))` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                        Mentés
                    </button>
                </div>
            </form>

            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Élő előnézet</h2>
                <p class="mt-1 text-xs text-muted">A kiválasztott séma világos és sötét változata (a mentés után ez lesz a tényleges hatás).</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="mode in ['light', 'dark']"
                        :key="mode"
                        class="overflow-hidden rounded-lg border"
                        :style="{ ...previewVars(activePalette[mode]), background: 'var(--p-bg)', borderColor: 'var(--p-border)', borderRadius: 'var(--p-radius)' }"
                    >
                        <div class="flex items-center justify-between border-b px-3 py-2" :style="{ background: 'var(--p-card)', borderColor: 'var(--p-border)' }">
                            <span class="text-[11px] font-bold uppercase tracking-wide" :style="{ color: 'var(--p-text)' }">
                                {{ mode === 'light' ? 'Világos' : 'Sötét' }}
                            </span>
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ background: 'var(--p-accent)' }" />
                        </div>
                        <div class="space-y-2.5 p-3">
                            <div class="rounded p-2.5" :style="{ background: 'var(--p-hi)', borderRadius: 'var(--p-radius)' }">
                                <p class="text-xs" :style="{ color: 'var(--p-text)' }">Kártya szöveg</p>
                                <p class="mt-0.5 text-[11px]" :style="{ color: 'var(--p-muted)' }">Halvány másodlagos szöveg</p>
                            </div>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-white"
                                :style="{ background: 'var(--p-accent)', borderRadius: 'var(--p-radius)' }"
                            >
                                Kosárba
                            </button>
                            <a href="#" class="block text-[11px] font-medium" :style="{ color: 'var(--p-accent)' }" @click.prevent>Kiemelt link</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.anim-demo-bar {
    opacity: 0;
    transform: translateY(var(--y, 16px));
    animation: anim-demo var(--d, 480ms) cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes anim-demo {
    to {
        opacity: 1;
        transform: none;
    }
}
</style>
