<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import MediaCard from '@/Components/MediaCard.vue';
import { useCollectionStore } from '@/Stores/collection';
import { useCartStore } from '@/Stores/cart';

const props = defineProps({
    shared: { type: Object, default: null },
});

const collection = useCollectionStore();
const cart = useCartStore();

const isShared = computed(() => props.shared !== null);
const items = ref(isShared.value ? props.shared.items : []);
const loading = ref(!isShared.value);
const shareUrl = ref(null);
const shareBusy = ref(false);

async function hydrate() {
    const ids = collection.ids;
    if (ids.length === 0) {
        loading.value = false;
        return;
    }
    try {
        const params = ids.map((id) => `ids[]=${id}`).join('&');
        const res = await fetch(`/api/cart?${params}`);
        const json = await res.json();
        const fresh = json.data ?? [];
        // Elavult / torolt media csendben kikerul a kollekciobol.
        const freshIds = fresh.map((m) => m.id);
        collection.items.filter((i) => !freshIds.includes(i.id)).forEach((i) => collection.remove(i.id));
        items.value = fresh;
    } catch (e) {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (!isShared.value) {
        hydrate();
    }
});

function removeItem(id) {
    collection.remove(id);
    items.value = items.value.filter((i) => i.id !== id);
}

function addAllToCart() {
    items.value.forEach((m) => cart.add({
        id: m.id,
        type: m.type,
        price_cents: m.price_cents,
        thumbnail_s3_key: m.thumbnail_s3_key,
        event_name: m.event?.name ?? '',
    }));
}

async function createShareLink() {
    shareBusy.value = true;
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/api/collection/share', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ?? '' },
            body: JSON.stringify({ media_ids: collection.ids }),
        });
        if (!res.ok) return;
        const json = await res.json();
        shareUrl.value = json.url;
        try {
            await navigator.clipboard.writeText(json.url);
        } catch (e) {
            // vagolapra masolas nem sikerult — a linket kiirjuk
        }
    } finally {
        shareBusy.value = false;
    }
}

const totalCents = computed(() => items.value.reduce((s, m) => s + (m.price_cents ?? 0), 0));
</script>

<template>
    <Head :title="isShared ? 'Megosztott kollekció' : 'Kollekcióm'" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content sm:text-3xl">
                    {{ isShared ? 'Megosztott kollekció' : 'Kollekcióm' }}
                </h1>
                <p class="mt-2 text-sm text-muted">
                    {{ isShared
                        ? 'Valaki megosztotta veled ezt a válogatást. Bármelyik felvételt kosárba teheted.'
                        : 'A „megfontolom” listád — nem a kosár. Amikor döntöttél, tedd át a kívánt tételeket a kosárba.' }}
                </p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div v-if="loading" class="text-sm text-muted">Betöltés…</div>

                <div v-else-if="items.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border p-10 text-center text-sm text-muted">
                    A kollekció üres.
                    <Link href="/events" class="ml-1 text-accent hover:underline">Böngészd a galériákat →</Link>
                </div>

                <template v-else>
                    <div class="mb-5 flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded-lg bg-accent px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover" @click="addAllToCart">
                            Mindent kosárba ({{ new Intl.NumberFormat('hu-HU').format(totalCents) }} Ft)
                        </button>
                        <button
                            v-if="!isShared"
                            type="button"
                            :disabled="shareBusy"
                            class="rounded-lg border border-border px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent disabled:opacity-60"
                            @click="createShareLink"
                        >
                            {{ shareBusy ? 'Link készítése…' : 'Megosztás linkkel' }}
                        </button>
                        <Link href="/cart" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content">Kosár →</Link>
                    </div>

                    <p v-if="shareUrl" class="mb-5 rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-3 text-xs text-content">
                        Megosztható link (7 napig él, a vágólapra másoltuk): <span class="break-all font-semibold">{{ shareUrl }}</span>
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="m in items" :key="m.id" class="relative">
                            <button
                                v-if="!isShared"
                                type="button"
                                class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-full bg-black/60 text-white hover:bg-accent"
                                aria-label="Eltávolítás"
                                @click="removeItem(m.id)"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </button>
                            <MediaCard :media="m" :event-name="m.event?.name ?? ''" @select="() => {}" />
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
