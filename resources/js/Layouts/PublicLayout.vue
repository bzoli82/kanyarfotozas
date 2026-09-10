<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { useCollectionStore } from '@/Stores/collection';
import { useConsentStore } from '@/Stores/consent';
import BrandLogo from '@/Components/BrandLogo.vue';
import SocialIcon from '@/Components/SocialIcon.vue';
import SeoHead from '@/Components/SeoHead.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    // Atlatszo, fotora ulo fejlec (csak a fooldal hero-jan) — a tartalom a header moge nyulik.
    transparentHeader: { type: Boolean, default: false },
});

const page = usePage();
const social = computed(() => page.props.social ?? []);

// Oldalváltás-átmenet — a <html data-anim-page> (blade / AnimationSettings) dönt.
const pageTransition = computed(() => {
    if (typeof document === 'undefined') return '';
    const mode = document.documentElement.dataset.animPage;
    if (!mode || mode === 'none') return '';
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return '';
    return mode === 'slide' ? 'page-slide' : 'page-fade';
});
const mobileOpen = ref(false);
const cart = useCartStore();
const collection = useCollectionStore();
const consent = useConsentStore();
const { t, locale } = useI18n();

const nav = computed(() => [
    { label: t('nav.home'), href: '/' },
    { label: t('nav.galleries'), href: '/events' },
    { label: t('nav.locations'), href: '/events?view=map' },
    { label: t('nav.photographers'), href: '/photographers' },
    { label: t('nav.pricing'), href: '/shop' },
    { label: t('nav.faq'), href: '/faq' },
    { label: t('nav.about'), href: '/about' },
    { label: t('nav.community'), href: '/social' },
    { label: t('nav.contact'), href: '/contact' },
]);

function setLocale(code) {
    if (code === locale.value) return;
    router.put(`/locale/${code}`, {}, { preserveScroll: true });
}

// Fejléc-fátyolüveg görgetéskor + görgetés-jelző csík (AnimationSettings kapcsolók).
const ds = typeof document !== 'undefined' ? document.documentElement.dataset : {};
const frostedHeaderOn = computed(() => props.transparentHeader && ds.animHeader !== 'off');
const progressOn = computed(() => ds.animProgress !== 'off');
const scrolled = ref(false);
const scrollPct = ref(0);
let scrollRaf = null;

function onScroll() {
    if (scrollRaf) return;
    scrollRaf = requestAnimationFrame(() => {
        scrollRaf = null;
        scrolled.value = window.scrollY > 40;
        const h = document.documentElement.scrollHeight - window.innerHeight;
        scrollPct.value = h > 0 ? Math.min(100, (window.scrollY / h) * 100) : 0;
    });
}

onMounted(() => {
    if (frostedHeaderOn.value || progressOn.value) {
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    if (scrollRaf) cancelAnimationFrame(scrollRaf);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-surface-0">
        <SeoHead />
        <div
            v-if="progressOn"
            class="fixed left-0 top-0 z-[60] h-[2px] bg-accent"
            :style="{ width: scrollPct + '%' }"
            aria-hidden="true"
        ></div>
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-accent focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
        >
            {{ t('common.skip_to_content') }}
        </a>
        <header
            class="z-40 w-full"
            :class="transparentHeader
                ? (frostedHeaderOn
                    ? ['fixed inset-x-0 top-0 transition-colors duration-300', scrolled ? 'border-b border-border/80 bg-surface-0/85 shadow-lg shadow-black/10 backdrop-blur' : 'bg-gradient-to-b from-black/70 via-black/30 to-transparent']
                    : 'absolute inset-x-0 top-0 bg-gradient-to-b from-black/70 via-black/30 to-transparent')
                : 'sticky top-0 border-b border-border/80 bg-surface-0/90 backdrop-blur'"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="font-display text-lg font-bold tracking-tight text-content">
                    <BrandLogo :variant="transparentHeader ? 'dark' : 'auto'" />
                </Link>

                <nav class="hidden items-center gap-7 md:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        :aria-current="page.url === item.href ? 'page' : undefined"
                        class="inline-block text-[13px] font-medium uppercase tracking-wide text-content/80 transition duration-300 ease-out hover:text-accent active:scale-90 motion-reduce:transition-none motion-reduce:active:scale-100 aria-[current=page]:text-accent"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-1 text-[11px] font-semibold uppercase tracking-wide sm:flex">
                        <button type="button" :class="locale === 'hu' ? 'text-accent' : 'text-content/50 hover:text-content'" @click="setLocale('hu')">HU</button>
                        <span class="text-content/30">/</span>
                        <button type="button" :class="locale === 'en' ? 'text-accent' : 'text-content/50 hover:text-content'" @click="setLocale('en')">EN</button>
                    </div>
                    <ThemeToggle class="hidden sm:inline-flex" />
                    <Link href="/collection" aria-label="Kollekcióm" class="relative text-content/80 hover:text-content">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" />
                        </svg>
                        <span
                            v-if="collection.count > 0"
                            class="absolute -right-2 -top-2 grid h-4 w-4 place-items-center rounded-full bg-accent text-[10px] font-bold text-white"
                        >
                            {{ collection.count }}
                        </span>
                    </Link>
                    <Link href="/cart" :aria-label="t('nav.cart')" class="relative text-content/80 hover:text-content">
                        <svg data-cart-target width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                        </svg>
                        <span
                            v-if="cart.count > 0"
                            class="absolute -right-2 -top-2 grid h-4 w-4 place-items-center rounded-full bg-accent text-[10px] font-bold text-white"
                        >
                            {{ cart.count }}
                        </span>
                    </Link>
                    <button
                        class="grid h-9 w-9 place-items-center rounded-lg border border-white/20 text-content md:hidden"
                        :aria-label="t('nav.menu')"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18M3 12h18M3 18h18" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="mobileOpen" class="border-t border-border bg-surface-1 md:hidden">
                <nav class="flex flex-col px-4 py-2">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="py-2.5 text-sm font-medium uppercase tracking-wide text-muted hover:text-content"
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                    <div class="flex items-center justify-between border-t border-border py-3">
                        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide">
                            <button type="button" :class="locale === 'hu' ? 'text-accent' : 'text-muted'" @click="setLocale('hu')">HU</button>
                            <span class="text-content/30">/</span>
                            <button type="button" :class="locale === 'en' ? 'text-accent' : 'text-muted'" @click="setLocale('en')">EN</button>
                        </div>
                        <ThemeToggle />
                    </div>
                </nav>
            </div>
        </header>

        <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
            <Transition :name="pageTransition" mode="out-in">
                <div :key="page.component"><slot /></div>
            </Transition>
        </main>

        <footer class="border-t border-border bg-surface-1">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div>
                    <div class="font-display text-base font-bold text-content">
                        <BrandLogo />
                    </div>
                    <p class="mt-3 max-w-xs text-sm text-muted">
                        {{ t('footer.tagline') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-muted lg:justify-center">
                    <Link href="/about" class="hover:text-content">{{ t('nav.about') }}</Link>
                    <Link href="/faq" class="hover:text-content">{{ t('footer.faq') }}</Link>
                    <Link href="/social" class="hover:text-content">{{ t('nav.community') }}</Link>
                    <Link href="/contact" class="hover:text-content">{{ t('nav.contact') }}</Link>
                    <Link href="/shop" class="hover:text-content">{{ t('nav.pricing') }}</Link>
                    <Link href="/csatlakozz" class="hover:text-content">{{ t('apply.title') }}</Link>
                    <Link href="/my-purchases" class="hover:text-content">Korábbi vásárlásaim</Link>
                    <Link href="/privacy" class="hover:text-content">{{ t('footer.privacy') }}</Link>
                    <Link href="/aszf" class="hover:text-content">{{ t('footer.terms') }}</Link>
                    <Link href="/impresszum" class="hover:text-content">{{ t('footer.impressum') }}</Link>
                </div>
                <div v-if="social.length" class="text-sm text-muted lg:text-right">
                    <div class="flex gap-4 lg:justify-end">
                        <a
                            v-for="s in social"
                            :key="s.platform"
                            :href="s.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="s.label"
                            :title="s.label"
                            class="text-muted transition-colors hover:text-content"
                        >
                            <SocialIcon :platform="s.platform" class="h-5 w-5" />
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-border">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 text-xs text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <span>© {{ new Date().getFullYear() }} {{ page.props.appName }}</span>
                    <span class="flex flex-wrap gap-4">
                        <Link href="/privacy" class="hover:text-content">{{ t('footer.data_handling') }}</Link>
                        <Link href="/adatvedelem/kerelem" class="hover:text-content">{{ t('footer.data_request') }}</Link>
                        <button type="button" class="hover:text-content" @click="consent.reset()">{{ t('footer.cookies') }}</button>
                    </span>
                </div>
            </div>
        </footer>

        <CookieConsent />
    </div>
</template>
