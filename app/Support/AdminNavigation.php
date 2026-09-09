<?php

namespace App\Support;

use App\Models\User;

/**
 * Az admin/fotós/szervező oldalsáv menüje EGY helyen, szerepkör szerint szűrve
 * és szekciókba rendezve, minden ponthoz egy rövid magyarázattal (`hint`).
 *
 * Ugyanezt a szerkezetet használja az `AdminLayout.vue` oldalsáv ÉS az
 * „Admin súgó" oldal (`/admin/guide`) — így a magyarázatok nem duplázódnak.
 */
class AdminNavigation
{
    /**
     * @return list<array{title: string, items: list<array{label: string, href: string, icon: string, hint: string}>}>
     */
    public static function sections(User $user): array
    {
        $role = (string) $user->role;
        $isSuper = $role === User::ROLE_SUPERADMIN;
        $isAdmin = in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true);
        $isOrganizer = $role === User::ROLE_ORGANIZER;

        if ($isOrganizer) {
            return [[
                'title' => 'Áttekintés',
                'items' => [
                    ['label' => 'Áttekintő', 'href' => '/organizer/dashboard', 'icon' => 'grid',
                        'hint' => 'A hozzád rendelt események statisztikája és a bevétel-részesedésed (csak megtekintés).'],
                ],
            ]];
        }

        $sections = [];

        // ─── Áttekintés ───────────────────────────────────────────────
        if ($isAdmin) {
            $sections[] = ['title' => 'Áttekintés', 'items' => array_values(array_filter([
                ['label' => 'Vezérlőpult', 'href' => '/admin/dashboard', 'icon' => 'grid',
                    'hint' => 'Napi kép: bevétel, rendelések, figyelmeztetések, konverziós tölcsér, médiaegészség.'],
                ['label' => 'Statisztikák', 'href' => '/admin/stats', 'icon' => 'stats',
                    'hint' => 'Szűrhető értékesítési kimutatás + CSV export. Időszakonként lebontva.'],
            ]))];
        } else {
            $sections[] = ['title' => 'Áttekintés', 'items' => [
                ['label' => 'Vezérlőpult', 'href' => '/photographer/dashboard', 'icon' => 'grid',
                    'hint' => 'A feltöltéseid állapota, az elszámolásod és a havi jutalék-bónuszod.'],
            ]];
        }

        // ─── Napi munka ───────────────────────────────────────────────
        $daily = [];
        if ($isAdmin) {
            $daily[] = ['label' => 'Rendelések', 'href' => '/admin/orders', 'icon' => 'cart',
                'hint' => 'Rendelések listája, keresés sorszámra, visszatérítés, számla, letöltő e-mail újraküldése.'];
            $daily[] = ['label' => 'Üzenetek', 'href' => '/admin/messages', 'icon' => 'mail',
                'hint' => 'Minden kapcsolati üzenet + a fotósoknak címzett kérdések. Válasz akár a fotós nevében is.'];
            $daily[] = ['label' => 'Események', 'href' => '/admin/events', 'icon' => 'calendar',
                'hint' => 'Események + médiák kezelése egy oldalon: feltöltés, tömeges import, árazás, törlés.'];
        } else {
            $daily[] = ['label' => 'Eseményeim', 'href' => '/admin/events', 'icon' => 'calendar',
                'hint' => 'A saját eseményeid + a hozzájuk tartozó képek/videók feltöltése és kezelése.'];
            $daily[] = ['label' => 'Üzenetek', 'href' => '/photographer/messages', 'icon' => 'mail',
                'hint' => 'A hozzád intézett kérdések (a média-oldali „Kérdés a fotóshoz" űrlapról) és a válaszaid.'];
        }
        $sections[] = ['title' => 'Napi munka', 'items' => $daily];

        // ─── Fotósok & szervezők (csak superadmin) ────────────────────
        if ($isSuper) {
            $sections[] = ['title' => 'Fotósok & szervezők', 'items' => [
                ['label' => 'Fotósok', 'href' => '/admin/photographers', 'icon' => 'users',
                    'hint' => 'Fotósok/adminok listája, meghívó, profil, jutalék %, kifizetések, nyilvános láthatóság-kapcsolók.'],
                ['label' => 'Szervező kifizetések', 'href' => '/admin/organizer-payouts', 'icon' => 'wallet',
                    'hint' => 'Az esemény-szervezőknek járó bevétel-részesedés kézi könyvelése.'],
            ]];
        }

        // ─── Saját fiók (mindenki) ────────────────────────────────────
        $sections[] = ['title' => 'Saját fiók', 'items' => [
            ['label' => 'Nyilvános profilom', 'href' => '/profil', 'icon' => 'idcard',
                'hint' => 'A „Fotósaink" oldalon megjelenő adataid: profilkép, bemutatkozó, elérhetőségek, láthatóság.'],
            ...($isAdmin ? [[
                'label' => 'Biztonság', 'href' => '/admin/settings/security', 'icon' => 'lock',
                'hint' => 'Kétfaktoros hitelesítés (2FA) be/ki a saját fiókodon. Superadmin kötelezővé teheti mindenkinek.',
            ]] : []),
        ]];

        // ─── A további szekciók csak superadminnak ────────────────────
        if ($isSuper) {
            $sections[] = ['title' => 'Tartalom', 'items' => [
                ['label' => 'Oldal neve', 'href' => '/admin/settings/branding', 'icon' => 'branding',
                    'hint' => 'A platform neve + a kétszínű fejléc-logó. A név mentése a vízjel szövegét is átírja.'],
                ['label' => 'Hero média', 'href' => '/admin/settings/hero', 'icon' => 'images',
                    'hint' => 'A főoldali diavetítés képei és videói (egymásba tűnnek). Ajánlott méretekkel.'],
                ['label' => 'E-mail sablonok', 'href' => '/admin/settings/mail', 'icon' => 'mail',
                    'hint' => '10 rendszer-e-mail (tárgy/fejléc/bevezető/lezárás/aláírás) szerkesztése, élő előnézettel.'],
                ['label' => 'Jogi oldalak', 'href' => '/admin/settings/legal', 'icon' => 'doc',
                    'hint' => 'Impresszum, ÁSZF és a Fotós Megállapodás (Markdown). A publikus /impresszum és /aszf tartalma.'],
                ['label' => 'Közösségi média', 'href' => '/admin/settings/social', 'icon' => 'share2',
                    'hint' => 'Facebook / Instagram / YouTube / TikTok linkek — a „Közösség" oldalon és a láblécben jelennek meg.'],
            ]];

            $sections[] = ['title' => 'Megjelenés', 'items' => [
                ['label' => 'Téma', 'href' => '/admin/settings/theme', 'icon' => 'theme',
                    'hint' => 'Színséma (világos+sötét pár), akcentszín, sarok-lekerekítés, betűtípus. Minden oldalra hat.'],
                ['label' => 'Vízjel', 'href' => '/admin/settings/watermark', 'icon' => 'watermark',
                    'hint' => 'A képekre/videókra égetett vízjel szövege, betűtípusa, mérete, sűrűsége — élő előnézettel.'],
            ]];

            $sections[] = ['title' => 'Kereshetőség', 'items' => [
                ['label' => 'SEO', 'href' => '/admin/settings/seo', 'icon' => 'search',
                    'hint' => 'Meta-leírás, OG-kép, Google Search Console azonosító + a globális „kereshetőség" kapcsoló.'],
                ['label' => 'GEO (AI-keresők)', 'href' => '/admin/settings/geo', 'icon' => 'robot',
                    'hint' => 'A /llms.txt (oldaltérkép az AI-crawlereknek: ChatGPT, Perplexity, Gemini) szerkesztése.'],
                ['label' => 'Helyszín-keresés', 'href' => '/admin/settings/location-search', 'icon' => 'pin',
                    'hint' => 'A GPS sugaras keresés (PostGIS) be/ki. Alapból KI — hosting-rugalmasság miatt.'],
            ]];

            $sections[] = ['title' => 'Rendszer', 'items' => [
                ['label' => 'Kritikus beállítások', 'href' => '/admin/settings/critical', 'icon' => 'shield',
                    'hint' => 'Állapot-áttekintő + fizetési kulcsok, e-mail (SMTP), számlázás, monitoring, webes ütemező, domain-átnevezés.'],
                ['label' => 'Tárhely', 'href' => '/admin/settings/storage', 'icon' => 'storage',
                    'hint' => 'NAS (SFTP) és Cloudflare R2 kulcsok, kapcsolat-teszt. A média hosszú távú tárolása.'],
                ['label' => 'Árazás', 'href' => '/admin/settings/pricing', 'icon' => 'wallet',
                    'hint' => 'Alap médiaár, automatikus mennyiségi kedvezmény és a fotós havi jutalék-bónusz sávjai.'],
                ['label' => 'Rendszámfelismerés', 'href' => '/admin/settings/plate-recognition', 'icon' => 'plate',
                    'hint' => 'Plate Recognizer OCR + automatikus rendszám-homályosítás. Teljesen kikapcsolható (alap: KI).'],
                ['label' => 'Éles ↔ helyi szinkron', 'href' => '/admin/settings/data-sync', 'icon' => 'sync',
                    'hint' => 'Az éles adatbázis + média letöltése a fejlesztői gépre (egyirányú, gombnyomásra).'],
            ]];

            $sections[] = ['title' => 'Napló & adatvédelem', 'items' => [
                ['label' => 'Hibanapló', 'href' => '/admin/errors', 'icon' => 'bug',
                    'hint' => 'A kezeletlen kivételek csoportosítva (előfordulás-szám, első/utolsó). Lezárás/újranyitás.'],
                ['label' => 'GDPR kérelmek', 'href' => '/admin/data-requests', 'icon' => 'privacy',
                    'hint' => 'A látogatói adat-export / -törlési kérelmek kezelése (JSON export, anonimizálás).'],
                ['label' => 'Kép-visszakövetés', 'href' => '/admin/forensics', 'icon' => 'fingerprint',
                    'hint' => 'Kiszivárgott képet feltöltve megmondja, melyik rendelésből / vásárlótól származik (láthatatlan jel).'],
            ]];
        }

        // ─── Súgó (mindenki, a lista végén) ───────────────────────────
        $sections[] = ['title' => 'Segítség', 'items' => [
            ['label' => 'Admin súgó', 'href' => '/admin/guide', 'icon' => 'help',
                'hint' => 'Minden menüpont egy helyen, rövid magyarázattal — ez az oldal.'],
        ]];

        return $sections;
    }
}
