<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const page = usePage();
const success = computed(() => page.props.flash?.success);

const form = useForm({
    email: '',
    type: 'export',
    website: '', // honeypot
});

function submit() {
    form.post('/adatvedelem/kerelem', { preserveScroll: true, onSuccess: () => form.reset('email') });
}
</script>

<template>
    <Head title="Adatkezelési kérelem" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">Adatkezelési kérelem</h1>
                <p class="mt-2 text-sm text-muted">
                    A GDPR alapján kérheted a rólad tárolt személyes adatok kiadását vagy törlését. Add meg azt az
                    e-mail címet, amellyel nálunk vásároltál vagy kapcsolatba léptél — a megadott címre küldünk egy
                    megerősítő hivatkozást, és a kérés csak annak megnyitása után indul el.
                </p>

                <div v-if="success" class="mt-6 rounded-[var(--radius-base)] border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-400">
                    {{ success }}
                </div>

                <form v-else class="mt-6 space-y-5 rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="submit">
                    <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">E-mail cím</span>
                        <input v-model="form.email" type="email" required class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-accent">{{ form.errors.email }}</p>
                    </label>

                    <fieldset class="space-y-2">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted">A kérés típusa</span>
                        <label class="flex items-center gap-2 text-sm text-content">
                            <input v-model="form.type" type="radio" value="export" class="accent-[var(--color-accent)]" />
                            Adatkiadás — megkapom a rólam tárolt adatok másolatát
                        </label>
                        <label class="flex items-center gap-2 text-sm text-content">
                            <input v-model="form.type" type="radio" value="delete" class="accent-[var(--color-accent)]" />
                            Törlés — töröljétek a személyes adataimat
                        </label>
                        <p v-if="form.type === 'delete'" class="text-[11px] text-muted">
                            A számviteli előírások miatt a kifizetett rendelések adatait megőrizzük, de az e-mail címed
                            anonimizáljuk és a letöltési linkek megszűnnek.
                        </p>
                    </fieldset>

                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                        Megerősítő e-mail kérése
                    </button>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
