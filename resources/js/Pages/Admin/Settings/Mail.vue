<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    templates: Array,
});

const activeKey = ref(props.templates[0]?.key ?? null);
const active = computed(() => props.templates.find((t) => t.key === activeKey.value));

const fieldLabels = {
    subject: 'Tárgy',
    heading: 'Címsor',
    intro: 'Bevezető szöveg',
    outro: 'Záró bekezdés',
    signature: 'Aláírás',
};

const multiline = ['intro', 'outro', 'signature'];

// Kulcsonkent kulon useForm, hogy a nem aktiv sablonok modositatlanok maradjanak.
const forms = {};
for (const tpl of props.templates) {
    forms[tpl.key] = useForm({
        subject: tpl.fields.subject ?? '',
        heading: tpl.fields.heading ?? '',
        intro: tpl.fields.intro ?? '',
        outro: tpl.fields.outro ?? '',
        signature: tpl.fields.signature ?? '',
    });
}

const form = computed(() => forms[activeKey.value]);

function save() {
    form.value.put(`/admin/settings/mail/${activeKey.value}`, { preserveScroll: true });
}

function resetToDefault() {
    if (!window.confirm('Biztosan visszaállítod ezt a sablont az alapértelmezettre?')) {
        return;
    }
    router.post(`/admin/settings/mail/${activeKey.value}/reset`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            const def = active.value.defaults;
            for (const f of active.value.editableFields) {
                form.value[f] = def[f] ?? '';
            }
            form.value.defaults();
        },
    });
}

function applySamples(text) {
    let out = text ?? '';
    for (const p of active.value.placeholders) {
        out = out.split(p.token).join(p.sample);
    }
    return out;
}

const previewSubject = computed(() => applySamples(form.value.subject));
const previewHeading = computed(() => applySamples(form.value.heading));
const previewIntro = computed(() => applySamples(form.value.intro));
const previewOutro = computed(() => applySamples(form.value.outro));
const previewSignature = computed(() => applySamples(form.value.signature));

function insertToken(token) {
    form.value.intro = `${form.value.intro ?? ''}${token}`;
}
</script>

<template>
    <Head title="E-mail sablonok" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">E-mail sablonok</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Az ügyfeleknek és fotósoknak kiküldött e-mailek szöveges részei testreszabhatók: tárgy, címsor, bevezető és záró
            szöveg, aláírás. A dinamikus tartalom (letöltési gombok, tétellista, riport-táblázat) automatikus marad.
            A <code class="text-accent">:kulcs</code> alakú helyőrzők beküldéskor a valós értékre cserélődnek.
        </p>

        <div class="mt-6 grid gap-6 lg:grid-cols-[220px_1fr]">
            <nav class="flex flex-col gap-1">
                <button
                    v-for="tpl in templates"
                    :key="tpl.key"
                    type="button"
                    class="rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors"
                    :class="tpl.key === activeKey ? 'bg-accent text-white' : 'text-muted hover:bg-surface-2 hover:text-content'"
                    @click="activeKey = tpl.key"
                >
                    {{ tpl.label }}
                    <span v-if="forms[tpl.key].isDirty" class="ml-1 text-xs">•</span>
                </button>
            </nav>

            <div v-if="active" class="grid gap-6 xl:grid-cols-2">
                <form class="space-y-4 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="save">
                    <p class="text-xs text-muted">{{ active.description }}</p>

                    <label v-for="f in active.editableFields" :key="f" class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ fieldLabels[f] }}</span>
                        <textarea
                            v-if="multiline.includes(f)"
                            v-model="form[f]"
                            rows="3"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        ></textarea>
                        <input
                            v-else
                            v-model="form[f]"
                            type="text"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                        />
                        <p v-if="form.errors[f]" class="mt-1 text-xs text-accent">{{ form.errors[f] }}</p>
                    </label>

                    <div>
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Használható helyőrzők</span>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="p in active.placeholders"
                                :key="p.token"
                                type="button"
                                :title="p.label"
                                class="rounded-md border border-border bg-surface-2 px-2 py-1 text-xs text-muted hover:border-accent hover:text-content"
                                @click="insertToken(p.token)"
                            >
                                {{ p.token }}
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-muted">Kattintásra a bevezető szöveg végére szúrja be.</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                            Mentés
                        </button>
                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="resetToDefault">
                            Alapértelmezett visszaállítása
                        </button>
                    </div>
                </form>

                <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Előnézet</h2>
                    <p class="mt-1 text-xs text-muted">Minta-értékekkel behelyettesítve.</p>

                    <div class="mt-4 rounded-lg border border-border bg-surface-2 p-4 text-sm text-content">
                        <p class="text-xs text-muted">Tárgy</p>
                        <p class="mb-4 font-semibold">{{ previewSubject }}</p>

                        <p class="font-display text-lg font-bold">{{ previewHeading }}</p>
                        <p v-if="previewIntro" class="mt-2 whitespace-pre-line text-muted">{{ previewIntro }}</p>

                        <div class="my-4 rounded-md bg-accent/15 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-accent">
                            [ dinamikus tartalom — gombok / lista / táblázat ]
                        </div>

                        <p v-if="previewOutro" class="mt-2 whitespace-pre-line text-muted">{{ previewOutro }}</p>
                        <p v-if="previewSignature" class="mt-4 whitespace-pre-line text-muted">{{ previewSignature }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
