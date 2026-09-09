<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from '@/Composables/useI18n';

const { t, locale } = useI18n();

// A jogi szoveg strukturaltan, nyelvenkent — a body statikus, szerzo altal
// kontrollalt HTML (nincs benne felhasznaloi input), ezert a v-html biztonsagos.
const hu = {
    title: 'Adatvédelmi tájékoztató',
    updated: 'Utolsó frissítés: 2026. szeptember',
    sections: [
        {
            title: '1. Az adatkezelő',
            html: '<p>A RoadsidePhoto platformot üzemeltető csapat. Elérhetőség adatvédelmi kérdésekben: a Kapcsolat oldalon.</p>',
        },
        {
            title: '2. Milyen adatokat kezelünk',
            html: '<ul><li><strong>Vásárláskor:</strong> e-mail cím (a letöltési link és a számlázás miatt), fizetési adatokat NEM tárolunk — azt a Stripe kezeli.</li><li><strong>Kapcsolatfelvételkor:</strong> név, e-mail cím, üzenet.</li><li><strong>Fotókon/videókon:</strong> az eseményeken készült felvételek járműveket és résztvevőket ábrázolhatnak.</li><li><strong>Technikai adatok:</strong> a sikertelen bejelentkezési kísérleteknél az IP-címet kizárólag hash-elt formában, biztonsági célból tároljuk.</li></ul>',
        },
        {
            title: '3. Rendszám-adatkezelés és homályosítás',
            html: '<p>A nyilvános, ingyenes előnézeti képeken és videókon a rendszerünk automatikusan felismeri és elhomályosítja a látható rendszámtáblákat, mielőtt a felvétel bárki számára megjelenne a galériában. A vízjel nélküli, megvásárolt fájlon a rendszám továbbra is homályosított marad, kivéve, ha a felvételen szereplő jármű tulajdonosa maga vásárolja meg a saját képét és kifejezetten kéri az eredeti változatot.</p><p>Ha egy nyilvános felvételen a homályosítás nem sikerült tökéletesen, vagy szeretnéd, hogy egy rólad készült felvételt eltávolítsunk, a Kapcsolat oldalon jelezd — 72 órán belül intézkedünk.</p>',
        },
        {
            title: '4. Az adatkezelés jogalapja és időtartama',
            html: '<ul><li>Vásárlási adatok: szerződés teljesítése (GDPR 6. cikk (1) b), a számviteli előírások szerinti megőrzési idővel.</li><li>Kapcsolatfelvételi üzenetek: jogos érdek (GDPR 6. cikk (1) f), az ügy lezárása után legfeljebb 1 évig.</li><li>Biztonsági naplók: jogos érdek, legfeljebb 90 napig.</li></ul>',
        },
        {
            title: '5. Adatfeldolgozók',
            html: '<p>Stripe (fizetés), tárhely- és e-mail szolgáltató. Harmadik félnek marketing célból adatot nem adunk át.</p>',
        },
        {
            title: '6. A te jogaid',
            html: '<p>Kérheted a rólad tárolt adatok másolatát, helyesbítését vagy törlését, valamint tiltakozhatsz az adatkezelés ellen. Panasszal a Nemzeti Adatvédelmi és Információszabadság Hatósághoz (NAIH) fordulhatsz.</p>',
        },
        {
            id: 'cookies',
            title: '7. Sütik (cookie-k)',
            html: '<p>Kizárólag a működéshez feltétlenül szükséges sütiket használunk: a bejelentkezési munkamenet fenntartásához és a kosár tartalmának megjegyzéséhez (a kosár a böngésződ helyi tárolójában, <code>localStorage</code>-ban él, nem a szervereinken). Marketing- és nyomkövető sütiket nem helyezünk el, ezért süti-beleegyező sávra sincs szükség.</p>',
        },
    ],
};

const en = {
    title: 'Privacy Policy',
    updated: 'Last updated: September 2026',
    sections: [
        {
            title: '1. Data controller',
            html: '<p>The team operating the RoadsidePhoto platform. For privacy matters, reach us via the Contact page.</p>',
        },
        {
            title: '2. What data we process',
            html: '<ul><li><strong>On purchase:</strong> email address (for the download link and invoicing). We do NOT store payment details — those are handled by Stripe.</li><li><strong>On contact:</strong> name, email address, message.</li><li><strong>In photos/videos:</strong> footage taken at events may show vehicles and participants.</li><li><strong>Technical data:</strong> for failed login attempts we store the IP address only in hashed form, for security purposes.</li></ul>',
        },
        {
            title: '3. Licence plates and blurring',
            html: '<p>On the free public preview images and videos our system automatically detects and blurs visible licence plates before the footage becomes visible to anyone in the gallery. On the purchased, watermark-free file the plate stays blurred, unless the owner of the vehicle in the shot buys their own image and explicitly requests the original version.</p><p>If blurring on a public shot was imperfect, or you would like footage of you removed, let us know on the Contact page — we act within 72 hours.</p>',
        },
        {
            title: '4. Legal basis and retention',
            html: '<ul><li>Purchase data: performance of a contract (GDPR Art. 6(1)(b)), retained per accounting rules.</li><li>Contact messages: legitimate interest (GDPR Art. 6(1)(f)), for up to 1 year after the case is closed.</li><li>Security logs: legitimate interest, for up to 90 days.</li></ul>',
        },
        {
            title: '5. Data processors',
            html: '<p>Stripe (payment), hosting and email providers. We do not share data with third parties for marketing purposes.</p>',
        },
        {
            title: '6. Your rights',
            html: '<p>You may request a copy, correction or deletion of the data we hold about you, and object to processing. You may lodge a complaint with the Hungarian National Authority for Data Protection and Freedom of Information (NAIH).</p>',
        },
        {
            id: 'cookies',
            title: '7. Cookies',
            html: '<p>We only use cookies strictly necessary for operation: keeping the login session and remembering the cart contents (the cart lives in your browser\'s <code>localStorage</code>, not on our servers). We set no marketing or tracking cookies, so no cookie consent bar is needed.</p>',
        },
    ],
};

const content = computed(() => (locale.value === 'en' ? en : hu));
</script>

<template>
    <Head :title="content.title" />

    <PublicLayout>
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 class="font-display text-3xl font-bold uppercase tracking-tight text-content sm:text-4xl">{{ content.title }}</h1>
                <p class="mt-3 text-xs text-muted">{{ content.updated }}</p>
            </div>
        </section>

        <section>
            <div class="prose-privacy mx-auto max-w-3xl space-y-8 px-4 py-10 text-sm leading-relaxed text-muted sm:px-6 lg:px-8">
                <div v-for="(s, i) in content.sections" :key="i" :id="s.id">
                    <h2 class="text-base font-semibold text-content">{{ s.title }}</h2>
                    <div class="mt-2 space-y-2" v-html="s.html" />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.prose-privacy :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
    margin-top: 0.5rem;
}
.prose-privacy :deep(li) {
    margin-top: 0.25rem;
}
.prose-privacy :deep(strong) {
    color: var(--color-content);
}
.prose-privacy :deep(code) {
    border-radius: 0.25rem;
    background: var(--color-surface-2);
    padding: 0 0.25rem;
}
</style>
