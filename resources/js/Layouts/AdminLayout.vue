<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import BrandLogo from '@/Components/BrandLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => ['superadmin', 'admin'].includes(user.value?.role));
const isSuperadmin = computed(() => user.value?.role === 'superadmin');
const isOrganizer = computed(() => user.value?.role === 'organizer');

const adminNav = computed(() => {
    const items = [
        { label: 'Dashboard', href: '/admin/dashboard', icon: 'grid' },
        { label: 'Statisztikák', href: '/admin/stats', icon: 'stats' },
        { label: 'Rendelések', href: '/admin/orders', icon: 'cart' },
        { label: 'Üzenetek', href: '/admin/messages', icon: 'mail' },
        { label: 'Események', href: '/admin/events', icon: 'calendar' },
        { label: 'Biztonság', href: '/admin/settings/security', icon: 'lock' },
    ];

    if (isSuperadmin.value) {
        items.push({ label: 'Fotósok', href: '/admin/photographers', icon: 'users' });
        items.push({ label: 'Kritikus beállítások', href: '/admin/settings/critical', icon: 'shield' });
        items.push({ label: 'Szervező kifizetések', href: '/admin/organizer-payouts', icon: 'wallet' });
        items.push({ label: 'Kép-visszakövetés', href: '/admin/forensics', icon: 'fingerprint' });
        items.push({ label: 'GDPR kérelmek', href: '/admin/data-requests', icon: 'privacy' });
        items.push({ label: 'Hibanapló', href: '/admin/errors', icon: 'bug' });
        items.push({ label: 'Oldal neve', href: '/admin/settings/branding', icon: 'branding' });
        items.push({ label: 'Tárhely', href: '/admin/settings/storage', icon: 'storage' });
        items.push({ label: 'Vízjel', href: '/admin/settings/watermark', icon: 'watermark' });
        items.push({ label: 'Hero média', href: '/admin/settings/hero', icon: 'images' });
        items.push({ label: 'Rendszámfelismerés', href: '/admin/settings/plate-recognition', icon: 'plate' });
        items.push({ label: 'E-mail sablonok', href: '/admin/settings/mail', icon: 'mail' });
        items.push({ label: 'SEO', href: '/admin/settings/seo', icon: 'search' });
        items.push({ label: 'GEO (AI-keresők)', href: '/admin/settings/geo', icon: 'robot' });
        items.push({ label: 'Helyszín-keresés', href: '/admin/settings/location-search', icon: 'pin' });
        items.push({ label: 'Jogi oldalak', href: '/admin/settings/legal', icon: 'doc' });
        items.push({ label: 'Közösségi média', href: '/admin/settings/social', icon: 'share2' });
        items.push({ label: 'Téma', href: '/admin/settings/theme', icon: 'theme' });
    }

    return items;
});

const photographerNav = [
    { label: 'Dashboard', href: '/photographer/dashboard', icon: 'grid' },
    { label: 'Eseményeim', href: '/admin/events', icon: 'calendar' },
    { label: 'Üzenetek', href: '/photographer/messages', icon: 'mail' },
];

const organizerNav = [
    { label: 'Áttekintő', href: '/organizer/dashboard', icon: 'grid' },
];

const nav = computed(() => {
    if (isAdmin.value) return adminNav.value;
    if (isOrganizer.value) return organizerNav;
    return photographerNav;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-surface-0">
        <aside class="hidden w-60 shrink-0 flex-col border-r border-border bg-surface-1 lg:flex">
            <div class="border-b border-border px-5 py-4">
                <Link href="/" class="font-display block text-base font-bold tracking-tight text-content">
                    <BrandLogo />
                </Link>
                <p class="mt-0.5 text-[11px] uppercase tracking-wide text-muted">
                    {{ isAdmin ? 'Admin felület' : isOrganizer ? 'Szervező felület' : 'Fotós felület' }}
                </p>
            </div>

            <nav class="flex-1 space-y-0.5 px-3 py-4">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="page.url.startsWith(item.href) ? 'page' : undefined"
                    class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition-colors hover:bg-surface-2 hover:text-content"
                    :class="{ 'bg-surface-2 text-content': page.url.startsWith(item.href) }"
                >
                    <svg v-if="item.icon === 'grid'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
                    <svg v-else-if="item.icon === 'users'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                    <svg v-else-if="item.icon === 'stats'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18" /><path d="M7 14l4-4 3 3 5-6" /></svg>
                    <svg v-else-if="item.icon === 'storage'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5" /><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3" /></svg>
                    <svg v-else-if="item.icon === 'watermark'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16M7 15l3-10 3 10M8.5 11h3" /></svg>
                    <svg v-else-if="item.icon === 'theme'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none" /></svg>
                    <svg v-else-if="item.icon === 'plate'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2" /><path d="M6 10v4M10 10v4M14 10v4M18 10v4" /></svg>
                    <svg v-else-if="item.icon === 'images'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-4.5-4.5L7 20" /></svg>
                    <svg v-else-if="item.icon === 'mail'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                    <svg v-else-if="item.icon === 'branding'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3M9 20h6M12 4v16" /></svg>
                    <svg v-else-if="item.icon === 'shield'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" /><path d="m9 12 2 2 4-4" /></svg>
                    <svg v-else-if="item.icon === 'cart'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /><path d="M2 3h2l2.5 12.5A2 2 0 0 0 8.5 17h9a2 2 0 0 0 2-1.6L21 7H5" /></svg>
                    <svg v-else-if="item.icon === 'lock'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                    <svg v-else-if="item.icon === 'bug'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="6" width="8" height="14" rx="4" /><path d="M19 7l-2 2M5 7l2 2M3 13h3M18 13h3M4 19l3-2M20 19l-3-2M9 3l1.5 2M15 3l-1.5 2" /></svg>
                    <svg v-else-if="item.icon === 'search'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    <svg v-else-if="item.icon === 'wallet'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M16 12h4M3 9h13" /></svg>
                    <svg v-else-if="item.icon === 'fingerprint'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 11c0 4-1 6-2 8M8 7a5 5 0 0 1 8 4c0 4 0 5 1 7M5 11a7 7 0 0 1 12-5M12 15c0 3 .5 4 1 5.5M19 13c0 4-.5 5-1 6.5" /></svg>
                    <svg v-else-if="item.icon === 'robot'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="8" width="16" height="12" rx="2" /><path d="M12 4v4M9 14h.01M15 14h.01M2 13h2M20 13h2" /></svg>
                    <svg v-else-if="item.icon === 'pin'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
                    <svg v-else-if="item.icon === 'privacy'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                    <svg v-else-if="item.icon === 'doc'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2" /></svg>
                    <svg v-else-if="item.icon === 'share2'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4" /></svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                    {{ item.label }}
                </Link>
            </nav>

            <div class="border-t border-border px-3 py-4">
                <div class="mb-3 flex items-center justify-between px-3">
                    <span class="text-sm text-content">{{ user?.name }}</span>
                    <ThemeToggle />
                </div>
                <div class="px-3 text-xs text-muted">{{ user?.email }}</div>
                <button
                    type="button"
                    class="mt-3 flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted hover:bg-surface-2 hover:text-content"
                    @click="logout"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></svg>
                    Kijelentkezés
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-border bg-surface-1 px-4 py-3 lg:hidden">
                <Link href="/" class="font-display text-base font-bold tracking-tight text-content">
                    <BrandLogo />
                </Link>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted" @click="logout">Kilépés</button>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-content"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-6 rounded-lg border border-accent bg-accent/20 px-4 py-3 text-sm text-content"
                >
                    {{ page.props.flash.error }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
