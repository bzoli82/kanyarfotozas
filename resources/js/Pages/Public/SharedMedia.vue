<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
// A `mediaUrl` prop a megosztási link — a média-fájl URL-jét az `assetUrl` helper adja.
import { mediaUrl as assetUrl } from '@/Composables/useMediaUrl';

const brand = computed(() => usePage().props.branding?.name ?? 'RoadsidePhoto');

const props = defineProps({
    media: { type: Object, required: true },
    event: { type: Object, default: null },
    photographer: { type: String, default: null },
    mediaUrl: { type: String, required: true },
});

const title = computed(() => (props.event ? `${props.event.name} — ${brand.value}` : `${brand.value} felvétel`));

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}
</script>

<template>
    <Head>
        <title>{{ title }}</title>
    </Head>

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <Link href="/" class="font-display mb-4 inline-block text-sm font-bold tracking-tight text-muted">
                    <BrandLogo />
                </Link>
                <div class="overflow-hidden rounded-[var(--radius-base)] border border-border bg-surface-1">
                    <VideoPlayer
                        v-if="media.type === 'video' && media.watermarked_s3_key"
                        :src="assetUrl(media.watermarked_s3_key)"
                        :hls="media.hls_playlist_s3_key ? assetUrl(media.hls_playlist_s3_key) : null"
                        class="aspect-video w-full"
                    />
                    <img v-else :src="assetUrl(media.watermarked_s3_key || media.thumbnail_s3_key)" class="w-full object-contain" alt="" />
                </div>

                <div class="mt-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">
                            {{ event ? event.name : `${brand} felvétel` }}
                        </h1>
                        <p class="mt-1 text-sm text-muted">
                            <span v-if="event">{{ event.location }} · </span>
                            <span v-if="photographer">Fotó: {{ photographer }}</span>
                        </p>
                    </div>
                    <Link :href="mediaUrl" class="rounded-lg bg-accent px-6 py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover">
                        Megvásárolom · {{ huf(media.price_cents) }}
                    </Link>
                </div>

                <p class="mt-6 text-xs text-muted">
                    Ez egy vízjeles előnézet. A megvásárolt fájl vízjel nélküli, eredeti felbontású.
                    <Link href="/events" class="text-accent hover:underline">Böngészd a többi felvételt →</Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
