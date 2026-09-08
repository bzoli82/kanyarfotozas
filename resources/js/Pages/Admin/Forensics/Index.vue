<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    enabled: Boolean,
    result: { type: Object, default: null },
});

const fileInput = ref(null);
const form = useForm({ image: null });

function onFile(e) {
    form.image = e.target.files?.[0] ?? null;
}

function identify() {
    if (!form.image) return;
    form.post('/admin/forensics/identify', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function toggle(e) {
    router.put('/admin/forensics/toggle', { enabled: e.target.checked }, { preserveScroll: true });
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0);
}

function dt(v) {
    return v ? new Date(v).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head title="Kép-visszakövetés" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Kép-visszakövetés</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            Minden megvásárolt, vízjel nélküli letöltésbe egy láthatatlan, a rendeléshez kötött jel kerül.
            Ha egy ilyen fájl kiszivárog, töltsd fel ide — a rendszer megmondja, melyik rendelésből (és így melyik vásárlótól) származik.
        </p>

        <div class="mt-4 flex items-center gap-3 rounded-[var(--radius-base)] border border-border bg-surface-1 px-4 py-3">
            <label class="flex items-center gap-2 text-sm text-content">
                <input type="checkbox" :checked="enabled" class="accent-[var(--color-accent)]" @change="toggle" />
                A forensic jel bekapcsolva (minden fotó-letöltésre)
            </label>
        </div>

        <form class="mt-6 max-w-xl rounded-[var(--radius-base)] border border-border bg-surface-1 p-5" @submit.prevent="identify">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Gyanús kép azonosítása</h2>
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/webp,image/png"
                class="mt-3 w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content file:mr-3 file:rounded file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:text-white"
                @change="onFile"
            />
            <p v-if="form.errors.image" class="mt-2 text-xs text-accent">{{ form.errors.image }}</p>
            <button
                type="submit"
                :disabled="form.processing || !form.image"
                class="mt-3 rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60"
            >
                {{ form.processing ? 'Elemzés…' : 'Azonosítás' }}
            </button>
        </form>

        <div v-if="result" class="mt-6 max-w-xl">
            <div v-if="!result.matched" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-sm text-muted">
                Nem található érvényes forensic jel a képen. Ez azt jelenti, hogy vagy nem ebből a rendszerből származik, vagy a jelet (metaadatot) eltávolították róla — pl. teljes újratömörítéssel.
            </div>

            <div v-else-if="result.orphaned" class="rounded-[var(--radius-base)] border border-accent/50 bg-surface-1 p-5 text-sm text-content">
                Érvényes jel — <strong>rendelés #{{ result.order_id }}</strong> —, de ez a rendelés már törölve lett a rendszerből.
            </div>

            <div v-else class="rounded-[var(--radius-base)] border border-accent bg-surface-1 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-accent">Azonosítva</div>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Rendelés</dt>
                        <dd class="text-content">
                            <a :href="`/admin/orders/${result.order_id}`" class="font-medium text-accent hover:text-accent-hover">{{ result.order_number }}</a>
                        </dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Vásárló</dt>
                        <dd class="text-content">{{ result.buyer_email }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Esemény</dt>
                        <dd class="text-content">{{ result.event ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Vásárlás ideje</dt>
                        <dd class="text-content">{{ dt(result.purchased_at) }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Fizetési állapot</dt>
                        <dd class="text-content">{{ result.payment_status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted">A jel kiadva</dt>
                        <dd class="text-content">{{ dt(result.issued_at) }}</dd>
                    </div>
                </dl>

                <div v-if="result.downloads?.length" class="mt-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-muted">Letöltések ehhez a képhez</div>
                    <ul class="mt-1 space-y-1 text-xs text-muted">
                        <li v-for="(d, i) in result.downloads" :key="i">{{ dt(d.at) }} · {{ d.format }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-8 max-w-2xl rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 text-xs text-muted">
            <strong class="text-content">Hogyan működik?</strong>
            A jel a fájl bájtjaiba kerül (JPEG-nél egy rejtett kommentszegmensbe + egy záró markerbe), kép-újrakódolás nélkül — így nem rontja a minőséget és gyors.
            A jel HMAC-kal aláírt, tehát nem hamisítható. <strong class="text-content">Korlát:</strong> szándékos metaadat-eltávolítással
            (pl. képszerkesztőben „Mentés másként", `exiftool -all=`) törölhető — ez tehát elrettentés és a gondatlan továbbadás visszakövetése, nem feltörhetetlen másolásvédelem.
        </div>
    </AdminLayout>
</template>
