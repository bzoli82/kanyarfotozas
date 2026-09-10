<script setup>
import { reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const props = defineProps({
    token: String,
    orderNumber: { type: String, default: null },
    invoiceUrl: { type: String, default: null },
    items: Array,
    expiresAt: String,
    usesLeft: Number,
    preparing: { type: Boolean, default: false },
});

const { t, locale } = useI18n();
const { mediaUrl } = useMediaUrl();

const formatLabels = { jpeg: 'JPEG', webp: 'WebP', mp4: 'MP4' };

// media_id -> { url, busy, copied }
const shareState = reactive({});

function fileUrl(mediaId, format) {
    return `/download/${props.token}/media/${mediaId}/${format}`;
}

async function share(item) {
    const state = shareState[item.id] ??= { url: null, busy: false, copied: false };
    if (!state.url) {
        state.busy = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(`/media/${item.id}/share`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf ?? '' },
                body: JSON.stringify({ download_token: props.token, platform: 'link' }),
            });
            if (!res.ok) { state.busy = false; return; }
            state.url = (await res.json()).url;
        } finally {
            state.busy = false;
        }
    }

    if (navigator.share) {
        navigator.share({ url: state.url, title: item.event?.name ?? 'RoadsidePhoto' }).catch(() => {});
    } else {
        try {
            await navigator.clipboard.writeText(state.url);
            state.copied = true;
            setTimeout(() => { state.copied = false; }, 2000);
        } catch (e) {
            // vagolap nem elerheto
        }
    }
}

function formatDate(iso) {
    return new Date(iso).toLocaleString(locale.value === 'en' ? 'en-GB' : 'hu-HU', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head :title="t('download.title')" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="font-display mb-4 text-sm font-bold tracking-tight text-muted">
                    <BrandLogo />
                </div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">{{ t('download.title') }}</h1>
                <p v-if="orderNumber" class="mt-1 text-xs font-semibold uppercase tracking-wide text-muted">
                    {{ t('download.order_number') }}: {{ orderNumber }}
                    <a v-if="invoiceUrl" :href="invoiceUrl" target="_blank" class="ml-2 text-accent hover:underline">{{ t('download.invoice') }}</a>
                </p>
                <p class="mt-1 text-sm text-muted">
                    {{ t('download.link_info', { date: formatDate(expiresAt), uses: usesLeft }) }}
                </p>

                <p v-if="preparing" class="mt-3 rounded-lg border border-border bg-surface-1 px-4 py-3 text-xs text-muted">
                    {{ t('download.preparing') }}
                </p>

                <div class="mt-6">
                    <a
                        :href="`/download/${token}/zip`"
                        class="inline-block rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                    >
                        {{ t('download.zip_all') }}
                    </a>
                </div>

                <div class="mt-6 overflow-hidden rounded-[var(--radius-base)] border border-border">
                    <div v-for="item in items" :key="item.id" class="flex items-center gap-4 border-b border-border bg-surface-1 p-3 last:border-b-0">
                        <div class="h-16 w-20 shrink-0 overflow-hidden rounded bg-surface-2">
                            <img v-if="item.thumbnail_s3_key" :src="mediaUrl(item.thumbnail_s3_key)" class="h-full w-full object-cover" alt="" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-content">{{ item.event?.name ?? t('cart.item_fallback', { id: item.id }) }}</p>
                            <p class="text-xs text-muted">{{ item.type === 'video' ? t('common.video') : t('common.photo') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a
                                v-for="format in item.download_formats"
                                :key="format"
                                :href="fileUrl(item.id, format)"
                                class="rounded-lg border border-border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-content hover:border-accent hover:text-accent"
                            >
                                {{ formatLabels[format] }}
                            </a>
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted hover:border-accent hover:text-accent"
                                :title="shareState[item.id]?.copied ? 'Link a vágólapon' : 'Megosztás'"
                                @click="share(item)"
                            >
                                <svg v-if="shareState[item.id]?.copied" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" /></svg>
                                <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <p class="mt-6 text-xs text-muted">
                    <Link href="/my-purchases" class="text-accent hover:underline">Többi vásárlásod megtekintése →</Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
