<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useCartStore } from '@/Stores/cart';
import { useI18n } from '@/Composables/useI18n';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const props = defineProps({
    paymentProviders: { type: Array, default: () => [] },
    billingRequired: { type: Boolean, default: false },
});

const { t } = useI18n();
const { mediaUrl } = useMediaUrl();
const cart = useCartStore();
const loading = ref(true);
const email = ref('');
const provider = ref(props.paymentProviders[0]?.id ?? null);
const couponCode = ref('');

const billing = ref({ billing_name: '', billing_country: 'HU', billing_zip: '', billing_city: '', billing_address: '', billing_tax_number: '' });
const isCompany = ref(false);

const billingCountries = [
    ['HU', 'Magyarország'], ['AT', 'Ausztria'], ['BE', 'Belgium'], ['BG', 'Bulgária'], ['HR', 'Horvátország'],
    ['CY', 'Ciprus'], ['CZ', 'Csehország'], ['DK', 'Dánia'], ['EE', 'Észtország'], ['FI', 'Finnország'],
    ['FR', 'Franciaország'], ['DE', 'Németország'], ['GR', 'Görögország'], ['IE', 'Írország'], ['IT', 'Olaszország'],
    ['LV', 'Lettország'], ['LT', 'Litvánia'], ['LU', 'Luxemburg'], ['MT', 'Málta'], ['NL', 'Hollandia'],
    ['PL', 'Lengyelország'], ['PT', 'Portugália'], ['RO', 'Románia'], ['SK', 'Szlovákia'], ['SI', 'Szlovénia'],
    ['ES', 'Spanyolország'], ['SE', 'Svédország'],
    ['GB', 'Egyesült Királyság'], ['CH', 'Svájc'], ['NO', 'Norvégia'], ['RS', 'Szerbia'], ['UA', 'Ukrajna'],
    ['US', 'Amerikai Egyesült Államok'], ['CA', 'Kanada'], ['AU', 'Ausztrália'],
];
const couponState = ref(null); // { valid, discount_cents, discount_percent, message }
const couponChecking = ref(false);
const submitting = ref(false);
const submitError = ref('');
const hasBlurredPlate = ref(false); // EPIC-13: van homályosított rendszámú tétel
const plateConsent = ref(false);
const termsAccepted = ref(false); // ÁSZF + adatvédelem + elállási jog lemondása (kötelező)

const subtotal = computed(() => cart.items.reduce((sum, item) => sum + (item.price_cents ?? 0), 0));
const discount = computed(() => (couponState.value?.valid ? couponState.value.discount_cents : 0));
const total = computed(() => Math.max(0, subtotal.value - discount.value));

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

onMounted(async () => {
    if (cart.items.length === 0) {
        loading.value = false;
        return;
    }

    try {
        const params = new URLSearchParams();
        cart.items.forEach((item) => params.append('ids[]', item.id));
        const res = await fetch(`/api/cart?${params.toString()}`);
        const json = await res.json();

        hasBlurredPlate.value = json.data.some((m) => m.plate_blurred);

        // Csak azok maradnak a kosarban, amik meg leteznek/lathatoak — az elavultak
        // (torolt/elrejtett media) csendben kikerulnek, friss arral szinkronizalva.
        cart.sync(
            json.data.map((m) => ({
                id: m.id,
                type: m.type,
                price_cents: m.price_cents,
                thumbnail_s3_key: m.thumbnail_s3_key,
                event_name: m.event?.name ?? '',
            })),
        );
    } finally {
        loading.value = false;
    }
});

let couponDebounce = null;
watch(couponCode, () => {
    couponState.value = null;
    clearTimeout(couponDebounce);
    if (!couponCode.value.trim()) return;
    couponDebounce = setTimeout(checkCoupon, 500);
});

async function checkCoupon() {
    if (!couponCode.value.trim()) return;
    couponChecking.value = true;
    try {
        const res = await fetch('/api/coupon/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({ code: couponCode.value.trim(), subtotal_cents: subtotal.value }),
        });
        couponState.value = await res.json();
    } catch (e) {
        couponState.value = { valid: false, message: t('cart.checkout_failed') };
    } finally {
        couponChecking.value = false;
    }
}

async function checkout() {
    submitError.value = '';

    if (!email.value.trim()) {
        submitError.value = t('cart.email_required');
        return;
    }

    if (props.billingRequired && (!billing.value.billing_name.trim() || !billing.value.billing_country)) {
        submitError.value = 'A számlához add meg a neved és az országod.';
        return;
    }

    if (!termsAccepted.value) {
        submitError.value = t('cart.terms_required');
        return;
    }

    submitting.value = true;
    try {
        const res = await fetch('/checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({
                media_ids: cart.items.map((item) => item.id),
                email: email.value.trim(),
                coupon_code: couponState.value?.valid ? couponCode.value.trim() : null,
                plate_consent: hasBlurredPlate.value ? plateConsent.value : false,
                terms_accepted: termsAccepted.value,
                provider: provider.value ?? undefined,
                ...(props.billingRequired ? {
                    ...billing.value,
                    billing_tax_number: isCompany.value ? billing.value.billing_tax_number.trim() : '',
                } : {}),
            }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => null);
            submitError.value = body?.message ?? Object.values(body?.errors ?? {})[0]?.[0] ?? t('cart.checkout_failed');
            return;
        }

        const { redirect_url: redirectUrl } = await res.json();
        window.location.href = redirectUrl;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="t('cart.title')" />

    <PublicLayout>
        <section>
            <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-bold uppercase tracking-tight text-content">{{ t('cart.title') }}</h1>

                <p v-if="loading" class="mt-6 text-sm text-muted">{{ t('common.loading') }}</p>

                <div v-else-if="cart.items.length === 0" class="mt-6 rounded-[var(--radius-base)] border border-dashed border-border bg-surface-1 p-10 text-center text-sm text-muted">
                    {{ t('cart.empty') }} <Link href="/events" class="text-accent hover:text-accent-hover">{{ t('cart.browse_events') }}</Link>
                </div>

                <div v-else class="mt-6 space-y-6">
                    <div class="overflow-hidden rounded-[var(--radius-base)] border border-border">
                        <div v-for="item in cart.items" :key="item.id" class="flex items-center gap-4 border-b border-border bg-surface-1 p-3 last:border-b-0">
                            <div class="h-16 w-20 shrink-0 overflow-hidden rounded bg-surface-2">
                                <img v-if="item.thumbnail_s3_key" :src="mediaUrl(item.thumbnail_s3_key)" class="h-full w-full object-cover" alt="" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <Link :href="`/media/${item.id}`" class="truncate text-sm font-medium text-content hover:text-accent">
                                    {{ item.event_name || t('cart.item_fallback', { id: item.id }) }}
                                </Link>
                                <p class="text-xs text-muted">{{ item.type === 'video' ? t('common.video') : t('common.photo') }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-content">{{ item.price_cents }} Ft</span>
                            <button type="button" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-muted hover:text-accent" @click="cart.remove(item.id)">
                                {{ t('cart.remove') }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('cart.email') }}</span>
                            <input
                                v-model="email"
                                type="email"
                                :placeholder="t('cart.email_placeholder')"
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none"
                            />
                        </label>

                        <fieldset v-if="billingRequired" class="mt-4 space-y-3 rounded-lg border border-border bg-surface-2/40 p-3">
                            <legend class="px-1 text-[11px] font-semibold uppercase tracking-wide text-muted">Számlázási adatok</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Név *</span>
                                    <input v-model="billing.billing_name" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Ország *</span>
                                    <select v-model="billing.billing_country" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none">
                                        <option v-for="[code, name] in billingCountries" :key="code" :value="code">{{ name }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Irányítószám</span>
                                    <input v-model="billing.billing_zip" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Város</span>
                                    <input v-model="billing.billing_city" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Cím</span>
                                    <input v-model="billing.billing_address" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                                </label>
                            </div>
                            <label class="flex items-center gap-2 text-[11px] text-muted">
                                <input v-model="isCompany" type="checkbox" class="accent-[var(--color-accent)]" /> Céges számlát kérek
                            </label>
                            <label v-if="isCompany" class="block">
                                <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Adószám</span>
                                <input v-model="billing.billing_tax_number" type="text" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none" />
                            </label>
                        </fieldset>

                        <label class="mt-3 block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('cart.coupon') }}</span>
                            <input
                                v-model="couponCode"
                                type="text"
                                class="w-full max-w-xs rounded-lg border border-border bg-surface-2 px-3 py-2.5 text-sm text-content focus:border-accent focus:outline-none"
                            />
                            <p v-if="couponChecking" class="mt-1 text-[11px] text-muted">{{ t('cart.coupon_checking') }}</p>
                            <p v-else-if="couponState?.valid" class="mt-1 text-[11px] font-semibold text-accent">
                                {{ t('cart.coupon_valid', { percent: couponState.discount_percent, amount: couponState.discount_cents }) }}
                            </p>
                            <p v-else-if="couponState && !couponState.valid" class="mt-1 text-[11px] text-accent">{{ couponState.message }}</p>
                        </label>

                        <div class="mt-4 space-y-1 border-t border-border pt-4">
                            <div class="flex items-center justify-between text-sm text-muted">
                                <span>{{ t('cart.subtotal') }}</span>
                                <span>{{ subtotal }} Ft</span>
                            </div>
                            <div v-if="discount > 0" class="flex items-center justify-between text-sm text-accent">
                                <span>{{ t('cart.discount') }}</span>
                                <span>-{{ discount }} Ft</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-sm text-muted">{{ t('cart.total') }}</span>
                                <span class="text-lg font-bold text-content">{{ total }} Ft</span>
                            </div>
                        </div>

                        <div v-if="paymentProviders.length > 1" class="mt-4">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('cart.payment_method') }}</span>
                            <div class="space-y-1.5">
                                <label
                                    v-for="p in paymentProviders"
                                    :key="p.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content"
                                    :class="{ 'border-accent': provider === p.id }"
                                >
                                    <input v-model="provider" type="radio" :value="p.id" class="accent-[var(--color-accent)]" />
                                    {{ p.label }}
                                </label>
                            </div>
                        </div>

                        <label v-if="hasBlurredPlate" class="mt-4 flex gap-2.5 rounded-lg border border-border bg-surface-2 p-3 text-xs text-muted">
                            <input v-model="plateConsent" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                            <span>
                                A megvásárolt felvételen a rendszámtáblát adatvédelmi okból elhomályosítottuk.
                                <span class="text-content">Pipáld ki, ha a felvételen szereplő jármű a tiéd</span>, és az eredeti,
                                homályosítás nélküli fájlt szeretnéd megkapni.
                            </span>
                        </label>

                        <label class="mt-4 flex gap-2.5 text-xs text-muted">
                            <input v-model="termsAccepted" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                            <span>
                                {{ t('cart.terms_label') }}
                                <span class="mt-0.5 block">
                                    <Link href="/aszf" target="_blank" class="text-accent hover:underline">{{ t('footer.terms') }}</Link>
                                    ·
                                    <Link href="/privacy" target="_blank" class="text-accent hover:underline">{{ t('footer.privacy') }}</Link>
                                </span>
                            </span>
                        </label>

                        <p v-if="submitError" class="mt-3 text-xs text-accent">{{ submitError }}</p>

                        <button
                            type="button"
                            :disabled="submitting || !termsAccepted"
                            class="mt-4 w-full rounded-lg bg-accent py-3 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60"
                            @click="checkout"
                        >
                            {{ submitting ? t('cart.redirecting') : t('cart.checkout') }}
                        </button>
                        <p class="mt-2 text-center text-[11px] text-muted">
                            {{ t('cart.stripe_note') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
