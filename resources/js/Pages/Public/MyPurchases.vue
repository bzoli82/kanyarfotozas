<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const { mediaUrl } = useMediaUrl();

const props = defineProps({
    verifiedEmail: { type: String, default: null },
    orders: { type: Array, default: () => [] },
    otpEmail: { type: String, default: null },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const otpSent = computed(() => Boolean(props.otpEmail));

const emailForm = useForm({ email: '' });
const otpForm = useForm({ email: '', otp: '' });

function requestOtp() {
    emailForm.post('/my-purchases/request-otp', {
        preserveScroll: true,
        onSuccess: () => {
            otpForm.email = emailForm.email;
        },
    });
}

function verify() {
    otpForm.email = otpForm.email || emailForm.email || props.otpEmail;
    otpForm.post('/my-purchases/verify', { preserveScroll: true });
}

function resend(orderId) {
    router.post('/my-purchases/resend', { order_id: orderId }, { preserveScroll: true });
}

function signOut() {
    router.post('/my-purchases/logout');
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('hu-HU', { year: 'numeric', month: 'long', day: 'numeric' });
}

function huf(cents) {
    return new Intl.NumberFormat('hu-HU').format(cents ?? 0) + ' Ft';
}
</script>

<template>
    <Head title="Korábbi vásárlásaim" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">Korábbi vásárlásaim</h1>
                <p class="mt-3 text-sm text-muted">
                    Add meg az e-mail címet, amivel vásároltál — küldünk egy 6 jegyű kódot, és megmutatjuk a rendeléseidet.
                </p>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="mb-6 rounded-[var(--radius-base)] border border-accent/40 bg-accent/10 p-4 text-sm text-content">
                    {{ flashSuccess }}
                </div>

                <!-- Bejelentkezett nezet -->
                <template v-if="verifiedEmail">
                    <div class="mb-6 flex items-center justify-between text-sm">
                        <span class="text-muted">Bejelentkezve: <span class="text-content">{{ verifiedEmail }}</span></span>
                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted hover:text-content" @click="signOut">Kilépés</button>
                    </div>

                    <div v-if="orders.length === 0" class="rounded-[var(--radius-base)] border border-dashed border-border p-8 text-center text-sm text-muted">
                        Ehhez az e-mail címhez nem tartozik fizetett rendelés.
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="order in orders" :key="order.id" class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-content">Rendelés {{ order.order_number ?? `#${order.id}` }}</p>
                                    <p class="text-xs text-muted">{{ formatDate(order.created_at) }} · {{ huf(order.total_cents) }} · {{ order.media_count }} tétel</p>
                                    <p v-if="order.events.length" class="mt-1 text-xs text-muted">{{ order.events.join(', ') }}</p>
                                </div>
                                <div class="flex -space-x-2">
                                    <img
                                        v-for="(thumb, i) in order.thumbnails"
                                        :key="i"
                                        :src="mediaUrl(thumb)"
                                        class="h-10 w-10 rounded border border-surface-1 object-cover"
                                        alt=""
                                    />
                                </div>
                            </div>

                            <div class="mt-3 border-t border-border pt-3">
                                <a
                                    v-if="order.token_active"
                                    :href="order.download_url"
                                    class="inline-block rounded-lg bg-accent px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover"
                                >
                                    Letöltés megnyitása
                                </a>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-lg border border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-content hover:border-accent"
                                    @click="resend(order.id)"
                                >
                                    Új letöltési link kérése
                                </button>
                                <span class="ml-3 text-[11px] text-muted">
                                    {{ order.token_active ? 'A link aktív' : 'A korábbi link lejárt' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- OTP folyamat -->
                <template v-else>
                    <form class="space-y-4" @submit.prevent="requestOtp">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">E-mail cím</span>
                            <input
                                v-model="emailForm.email"
                                type="email"
                                autocomplete="email"
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                            />
                            <p v-if="emailForm.errors.email" class="mt-1 text-xs text-accent">{{ emailForm.errors.email }}</p>
                        </label>
                        <button type="submit" :disabled="emailForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                            {{ otpSent ? 'Új kód küldése' : 'Kód kérése' }}
                        </button>
                    </form>

                    <form v-if="otpSent" class="mt-6 space-y-4 border-t border-border pt-6" @submit.prevent="verify">
                        <label class="block max-w-[200px]">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">6 jegyű kód</span>
                            <input
                                v-model="otpForm.otp"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-center text-lg tracking-[0.3em] text-content focus:border-accent focus:outline-none"
                            />
                            <p v-if="otpForm.errors.otp" class="mt-1 text-xs text-accent">{{ otpForm.errors.otp }}</p>
                        </label>
                        <button type="submit" :disabled="otpForm.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                            Belépés
                        </button>
                    </form>
                </template>

                <p class="mt-8 text-xs text-muted">
                    Még nem vásároltál? <Link href="/events" class="text-accent hover:underline">Böngészd a galériákat →</Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
