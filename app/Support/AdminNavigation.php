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
                ['label' => 'Tevékenység-napló', 'href' => '/admin/activity', 'icon' => 'doc',
                    'hint' => 'Ki mit módosított: rendelés, esemény, média, beállítás, visszatérítés. Szűrhető, kereshető.'],
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
                ['label' => 'Karbantartási mód', 'href' => '/admin/settings/maintenance', 'icon' => 'shield',
                    'hint' => 'A publikus oldal mögé egy „hamarosan" lapot tesz. Az admin + a fizetési webhookok elérhetők maradnak.'],
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

    /**
     * Kulcsszavas kereséshez: a menüpontok laposan (szekció-címmel) + néhány
     * „mélyen ülő" beállítás, amit nehéz megtalálni (pl. a Kritikus beállítások
     * alszekciói). A `keywords` extra találati szavak (szinonimák, angol, rövidítés).
     *
     * @return list<array{label: string, href: string, hint: string, section: string, icon: string, keywords: string}>
     */
    public static function searchIndex(User $user): array
    {
        $index = [];

        foreach (self::sections($user) as $section) {
            foreach ($section['items'] as $item) {
                $index[] = [
                    'label' => $item['label'],
                    'href' => $item['href'],
                    'hint' => $item['hint'],
                    'section' => $section['title'],
                    'icon' => $item['icon'],
                    'keywords' => '',
                ];
            }
        }

        $isSuper = (string) $user->role === User::ROLE_SUPERADMIN;
        $isAdmin = in_array((string) $user->role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true);

        $extras = [];

        if ($isAdmin) {
            $extras[] = ['label' => 'Új esemény létrehozása', 'href' => '/admin/events/create', 'section' => 'Napi munka', 'icon' => 'calendar',
                'hint' => 'Új fotózás felvétele (név, helyszín, dátum, árazás), majd média feltöltés.', 'keywords' => 'esemeny uj create hozzaadas rendezveny palyanap verseny'];
            $extras[] = ['label' => 'Kép / videó feltöltése (mobil)', 'href' => '/upload', 'section' => 'Napi munka', 'icon' => 'images',
                'hint' => 'Mobilra optimalizált feltöltő — a pálya mellől is.', 'keywords' => 'feltoltes upload mobil telefon kep video pwa'];
            $extras[] = ['label' => 'Visszatérítés egy rendelésre', 'href' => '/admin/orders', 'section' => 'Napi munka', 'icon' => 'cart',
                'hint' => 'A rendelés részletnézetében: teljes vagy részleges visszatérítés (Stripe / SimplePay / Barion).', 'keywords' => 'visszaterites refund penzvisszafizetes sztorno rendeles'];
            $extras[] = ['label' => 'Számla kiállítása / sztornó', 'href' => '/admin/orders', 'section' => 'Napi munka', 'icon' => 'doc',
                'hint' => 'A rendelés részletnézetében a „Számla" panel (Billingo).', 'keywords' => 'szamla invoice billingo sztorno pdf afa'];
        }

        if ($isSuper) {
            $c = '/admin/settings/critical';
            $extras[] = ['label' => 'Stripe kulcsok', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'shield',
                'hint' => 'Kritikus beállítások → Fizetés: Stripe secret / publishable / webhook secret.', 'keywords' => 'stripe fizetes kartya payment webhook kulcs'];
            $extras[] = ['label' => 'SimplePay beállítás', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'shield',
                'hint' => 'Kritikus beállítások → Fizetés: SimplePay merchant + secret key, sandbox.', 'keywords' => 'simplepay otp fizetes payment merchant'];
            $extras[] = ['label' => 'Barion beállítás', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'shield',
                'hint' => 'Kritikus beállítások → Fizetés: Barion POSKey + payee, sandbox.', 'keywords' => 'barion fizetes payment poskey smart gateway'];
            $extras[] = ['label' => 'E-mail küldés (SMTP)', 'href' => $c.'#mail', 'section' => 'Rendszer', 'icon' => 'mail',
                'hint' => 'Kritikus beállítások → E-mail: SMTP host/port/jelszó, feladó, Reply-To, tesztlevél.', 'keywords' => 'smtp email level kuldes mail felado reply-to tesztlevel'];
            $extras[] = ['label' => 'hCaptcha be/ki', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'shield',
                'hint' => 'Kritikus beállítások → Captcha: a hCaptcha widget bekapcsolása a Kapcsolat űrlapon.', 'keywords' => 'hcaptcha captcha robot spam kapcsolat urlap'];
            $extras[] = ['label' => 'Számlázás (Billingo)', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'doc',
                'hint' => 'Kritikus beállítások → Számlázás: Billingo API-kulcs + számlatömb, ÁFA, auto-számla.', 'keywords' => 'szamlazas billingo invoice afa nav szamlatomb'];
            $extras[] = ['label' => 'Automatikus mentés / backup', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'storage',
                'hint' => 'Kritikus beállítások → Monitoring és mentés: „Mentés most", a legutóbbi mentések, hiba-értesítés.', 'keywords' => 'backup mentes adatbazis pg_dump biztonsagi monitoring webhook'];
            $extras[] = ['label' => 'Webes ütemező (cron fallback)', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'sync',
                'hint' => 'Kritikus beállítások → Deploy-emlékeztetők: ha nincs rendes cron, egy titkos URL-t egy külső ütemezőbe.', 'keywords' => 'cron utemezo scheduler webscheduler feladat'];
            $extras[] = ['label' => 'Domain átnevezése', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'branding',
                'hint' => 'Kritikus beállítások → Az oldal neve / átnevezés: Előnézet + végrehajtás egy új domainre.', 'keywords' => 'domain atnevezes rename identity url'];
            $extras[] = ['label' => 'R2 CORS / deploy-emlékeztetők', 'href' => $c, 'section' => 'Rendszer', 'icon' => 'storage',
                'hint' => 'Kritikus beállítások → Deploy-emlékeztetők: a böngésző→R2 feltöltéshez szükséges CORS JSON.', 'keywords' => 'cors r2 cloudflare deploy feltoltes bucket'];

            $extras[] = ['label' => 'Karbantartási mód', 'href' => '/admin/settings/maintenance', 'section' => 'Kereshetőség', 'icon' => 'shield',
                'hint' => 'A publikus oldalt egy „hamarosan" lap mögé teszi; az admin elérhető marad.', 'keywords' => 'karbantartas maintenance hamarosan down offline zarva coming soon'];
            $extras[] = ['label' => 'Animációk (mozgás) beállítása', 'href' => '/admin/settings/theme', 'section' => 'Megjelenés', 'icon' => 'theme',
                'hint' => 'Téma → Animációk: oldalváltás, hero, kártya-hover, görgetés-reveal, GY.I.K. lenyílás, kosárba-repülés — mind ki/be.', 'keywords' => 'animacio mozgas atmenet transition hover parallax reveal'];
            $extras[] = ['label' => 'Feltölthető logó (kép)', 'href' => '/admin/settings/branding', 'section' => 'Tartalom', 'icon' => 'branding',
                'hint' => 'Oldal neve → Logó (kép): SVG/PNG feltöltés (fő + sötét háttérre); e-mailben és OG-képen is.', 'keywords' => 'logo svg png embléma marka markajel kep feltoltes'];
            $extras[] = ['label' => 'Mennyiségi kedvezmény', 'href' => '/admin/settings/pricing', 'section' => 'Rendszer', 'icon' => 'wallet',
                'hint' => 'Árazás → Mennyiségi kedvezmény: „N+ kép egy eseményből → X% kedvezmény".', 'keywords' => 'kedvezmeny mennyisegi bulk discount csomagar arazas'];
            $extras[] = ['label' => 'Fotós jutalék-bónusz', 'href' => '/admin/settings/pricing', 'section' => 'Rendszer', 'icon' => 'wallet',
                'hint' => 'Árazás → Fotós jutalék-bónusz: a havi eladásszámmal nő a fotós részesedése.', 'keywords' => 'jutalek bonusz reszesedes fotos commission bonus arazas'];
            $extras[] = ['label' => '2FA kötelezővé tétele', 'href' => '/admin/settings/security', 'section' => 'Saját fiók', 'icon' => 'lock',
                'hint' => 'Biztonság: a superadmin kötelezővé teheti a kétfaktoros hitelesítést minden adminnak.', 'keywords' => '2fa ketfaktoros totp kotelezo biztonsag mfa'];
            $extras[] = ['label' => 'Új fotós meghívása', 'href' => '/admin/photographers', 'section' => 'Fotósok & szervezők', 'icon' => 'users',
                'hint' => 'Fotósok → „Új fotós meghívása" — 48 órás linkkel, előre beállított jutalékkal.', 'keywords' => 'fotos meghivo invite uj regisztracio szerep'];
            $extras[] = ['label' => 'Fotós kifizetés (jutalék)', 'href' => '/admin/photographers', 'section' => 'Fotósok & szervezők', 'icon' => 'wallet',
                'hint' => 'A fotós részletnézetében a „Kifizetések" fül: jutalék-főkönyv, bizonylat, kifizetés.', 'keywords' => 'kifizetes payout jutalek elszamolas fotos bizonylat'];
        }

        return array_merge($index, $extras);
    }
}
