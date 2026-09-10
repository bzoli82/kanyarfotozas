<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';
import AdminCommandPalette from '@/Components/AdminCommandPalette.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => ['superadmin', 'admin'].includes(user.value?.role));
const isOrganizer = computed(() => user.value?.role === 'organizer');

// A menü szerkezete a szerverről jön (App\Support\AdminNavigation) — szekciókba
// rendezve, minden ponthoz egy `hint` magyarázattal (tooltip + Admin súgó oldal).
const sections = computed(() => page.props.adminNav ?? []);

// Összecsukott szekciók megjegyzése (böngészőnként).
const STORAGE_KEY = 'roadsidephoto.admin_nav_collapsed';
const collapsed = reactive({});
try {
    Object.assign(collapsed, JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'));
} catch (e) { /* localStorage nem elérhető */ }

function toggleSection(title) {
    collapsed[title] = !collapsed[title];
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsed));
    } catch (e) { /* ignore */ }
}

function isActive(href) {
    // /profil és /admin/events pontos illeszkedés, a többi prefix (aloldalak is aktívak)
    return href === '/profil' || href === '/admin/events'
        ? page.url === href || page.url.startsWith(href + '?')
        : page.url.startsWith(href);
}

const mobileOpen = ref(false);
const cmdp = ref(null);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-surface-0">
        <AdminCommandPalette ref="cmdp" />
        <aside class="hidden w-60 shrink-0 flex-col border-r border-border bg-surface-1 lg:flex">
            <div class="border-b border-border px-5 py-4">
                <Link href="/" class="font-display block text-base font-bold tracking-tight text-content">
                    <BrandLogo />
                </Link>
                <p class="mt-0.5 text-[11px] uppercase tracking-wide text-muted">
                    {{ isAdmin ? 'Admin felület' : isOrganizer ? 'Szervező felület' : 'Fotós felület' }}
                </p>
            </div>

            <div class="px-3 pt-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg border border-border bg-surface-2 px-3 py-2 text-left text-xs text-muted transition-colors hover:border-accent hover:text-content"
                    @click="cmdp?.show()"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    <span class="flex-1 truncate">Keresés a menüben…</span>
                    <kbd class="hidden shrink-0 rounded border border-border px-1 text-[10px] xl:inline">Ctrl K</kbd>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <div v-for="section in sections" :key="section.title" class="mb-1">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted/70 hover:text-muted"
                        @click="toggleSection(section.title)"
                    >
                        <span>{{ section.title }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="transition-transform" :class="{ '-rotate-90': collapsed[section.title] }"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div v-show="!collapsed[section.title]" class="mt-0.5 space-y-0.5">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            :title="item.hint"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-muted transition-colors hover:bg-surface-2 hover:text-content"
                            :class="{ 'bg-surface-2 text-content': isActive(item.href) }"
                        >
                            <AdminNavIcon :name="item.icon" class="shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="border-t border-border px-3 py-4">
                <div class="mb-3 flex items-center justify-between px-3">
                    <span class="truncate text-sm text-content">{{ user?.name }}</span>
                    <ThemeToggle />
                </div>
                <div class="truncate px-3 text-xs text-muted">{{ user?.email }}</div>
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
                <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-border text-content" aria-label="Menü" @click="mobileOpen = !mobileOpen">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                </button>
                <Link href="/" class="font-display text-base font-bold tracking-tight text-content">
                    <BrandLogo />
                </Link>
                <div class="flex items-center gap-3">
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-border text-content" aria-label="Keresés a menüben" @click="cmdp?.show()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    </button>
                    <ThemeToggle />
                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-muted" @click="logout">Kilépés</button>
                </div>
            </header>

            <!-- Mobil menü -->
            <div v-if="mobileOpen" class="border-b border-border bg-surface-1 lg:hidden">
                <nav class="max-h-[70vh] overflow-y-auto px-3 py-3">
                    <div v-for="section in sections" :key="section.title" class="mb-2">
                        <p class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted/70">{{ section.title }}</p>
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted hover:bg-surface-2 hover:text-content"
                            :class="{ 'bg-surface-2 text-content': isActive(item.href) }"
                            @click="mobileOpen = false"
                        >
                            <AdminNavIcon :name="item.icon" class="shrink-0" />
                            {{ item.label }}
                        </Link>
                    </div>
                </nav>
            </div>

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
