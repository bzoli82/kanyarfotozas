# RoadsidePhoto Platform — fejlesztési összefoglaló

*Frissítve: 2026-09-10 · Állapot: minden tervezett funkció kész, **571 automata teszt zöld**, a projekt git alatt (`github.com/bzoli82/roadsidephoto`), CI zöld*

> **Márkanév: RoadsidePhoto** · domain: `roadsidephoto.eu` · logó: `ROADSIDE` (fehér) + `PHOTO` (accent), vagy adminból feltöltött kép.
> A `kanyarfotozas → roadsidephoto` átnevezés **teljes** (kód, adatbázis, artisan-névtér `roadsidephoto:*`, git repo, `site_domain`). A magyar „kanyar" szó mint termékleírás szándékosan MARAD a copy-ban.

---

## 1. Mi ez?

Magyar motorsport **fotó / -videó értékesítő platform**. A fotósok pályanapokon,
versenyeken, találkozókon fotózzák a résztvevőket; a résztvevők helyszín és időpont szerint
megkeresik a magukról készült felvételeket, és **regisztráció nélkül**, csak e-mail-címmel
megvásárolják, vízjel nélkül letöltik.

- **Vásárló**: nem regisztrál, e-mail + kártyás fizetés, 72 órás letöltési link.
- **Fotós**: feltölti a saját anyagát, a rendszer intézi a vízjelezést / webshopot / kifizetést,
  a fotós az eladás előre megbeszélt százalékát kapja.
- **Üzemeltető (superadmin)**: teljes admin felület minden beállításhoz.

---

## 2. Technológia

| Réteg | Megoldás |
|---|---|
| Backend | Laravel 13 (PHP 8.4), session-alapú auth, Spatie Permission + ActivityLog |
| Frontend | Vue 3 + Inertia.js (SPA), Tailwind CSS 4, Leaflet térkép |
| Adatbázis | PostgreSQL 18 + **PostGIS** (GPS sugaras keresés) |
| Képfeldolgozás | Intervention Image (GD) — WebP thumbnail, csempézett vízjel, rendszám-homályosítás |
| Videófeldolgozás | FFmpeg — 720p vízjeles előnézet, scrub-sprite, HLS stream |
| Tárhely | Fejlesztésben lokális disk; **élesben Cloudflare R2** (2 bucket, env-vezérelt, kód nem változik) |
| Fizetés | Stripe Checkout + SimplePay v2 + Barion Smart Gateway v2 (mindegyik adminból ki/be kapcsolható) |
| Számlázás | Billingo v3 (automatikus számla a sikeres fizetés után) |
| E-mail | Laravel markdown mailek, sablonszerkesztővel |

---

## 3. Szerepkörök

`superadmin > admin > photographer` · `organizer` (különálló, korlátozott)

- **photographer**: csak a saját `photographer_id`-jéhez tartozó médiát/eseményt látja és kezeli.
- **admin**: teljes rendeléskezelés, események, statisztika, hibanapló.
- **superadmin**: minden beállítás (fizetés, tárhely, téma, SEO, biztonság, e-mail sablonok, fotósok, kifizetések, GDPR).
- **organizer** (esemény-szervező): saját, READ-ONLY portál (`/organizer/dashboard`) — a hozzá rendelt események
  statisztikája és a bevétel-részesedése; semmit nem szerkeszthet.

---

## 4. Publikus oldalak (látogatói felület)

| Útvonal | Tartalom |
|---|---|
| `/` | Főoldal — **hero diavetítés** (admin által feltölthető kép + videó) + a **közös keresőpanel** (ország / helyszín-autocomplete / dátum / fotós / típus + GPS-fül + térkép) |
| `/events` | Esemény-lista — a lap tetején **ugyanaz a keresőpanel**, alatta a lapozható galéria (GPS sugaras PostGIS-keresés is) |
| `/events/{slug}` | Esemény-galéria — fotó/videó kártyák, videónál hover-play + scrub-sáv, **lightbox** léptetéssel, időpont-hisztogram, HH:MM kereső. **Klasszikus lapozás** (10/25/50/100/250/500 kép/oldal, „1–10 / 15 fotó" kiírás); a lightbox a szélső képnél átlép a szomszéd oldalra |
| `/csatlakozz` | **„Csatlakozz fotósként"** jelentkezési űrlap (portfólió-link, régió, bemutatkozás) — az admin „Üzenetek" felületén landol |
| `/media/{id}` | Média részletnézet + „hasonló időpontban készült" javaslatok |
| `/cart` → `/checkout` | Kosár (Pinia + localStorage), kuponkód, fizetési mód választó, számlázási adatok (ha van számlázó) |
| `/download/{token}` | Letöltés — tételenként JPEG/WebP/MP4 + „összes ZIP-ben" + számla PDF; 72 óra / max 5 oldal-megnyitás |
| `/collection` | Kollekció / wishlist (kosártól külön), megosztható link |
| `/share/{token}` | Megvásárolt média publikus, **vízjeles** megosztó oldala |
| `/my-purchases` | „Korábbi vásárlásaim" — OTP-s e-mail-verifikációval |
| `/photographers` | Fotósaink — kör alakú profilkép + név + bemutatkozó (a nyilvános profilú fotósok és adminok) |
| `/about` `/shop` `/faq` `/contact` `/privacy` | Információs oldalak (a Kapcsolat űrlap védelme: time-trap + számtani kérdés / opcionális hCaptcha + proof-of-work + honeypot + rétegzett rate limit) |
| `/aszf` `/impresszum` | ÁSZF + Impresszum — adminból szerkeszthető Markdown (superadmin) |
| `/adatvedelem/kerelem` | GDPR adatkiadási / törlési kérelem |
| `/sitemap.xml` `/robots.txt` | Dinamikusan generált (SEO) |

**Kétnyelvű** (HU/EN) a teljes publikus felület. Az admin szándékosan magyar.

---

## 5. Admin felület — teljes aloldal-lista

### Működés
- **Dashboard** (`/admin/dashboard`) — 8 KPI kártya, 5 Chart.js diagram, legfrissebb események/rendelések,
  **proaktív figyelmeztetések**, konverziós tölcsér, **esemény-szintű teljesítmény** (megtekintés → rendelés → fizetett → bevétel eseményenként),
  médiaegészség, bevétel-előrejelzés (lineáris regresszió), fotós-összehasonlítás, biztonsági riasztás panel.
- **Statisztikák** (`/admin/stats`) — szűrhető értékesítési táblázat + CSV export (időszakos rollup is).
- **Rendelések** (`/admin/orders`) — lista/keresés **sorszám** szerint is (`{PREFIX}-{ÉV}-{6 jegy}`),
  részletek + **eseménynapló**, **visszatérítés** (teljes/részleges, Stripe + SimplePay + Barion), letöltő e-mail újraküldése,
  számla kiállítás / sztornó / PDF.
- **Fotós kifizetések** — a **Fotósok** admin lapon: a lista alatt „kifizetetlen jutalék összesen" +
  „eddig kifizetve" számláló, fotósonként „nyitott jutalék" oszlop; a fotós nevére kattintva a
  részletnézet **„Kifizetések" füle** — jutalék-főkönyv, **kifizetési bizonylat** előkészítése →
  kifizetettnek jelölés, CSV export.
- **Üzenetek** (`/admin/messages`) — MINDEN kapcsolati üzenetváltás (support + a feltöltő fotósoknak
  a média-oldali „Kérdés a fotóshoz" űrlapról címzett kérdések). Válasz bármely szálra (e-mailben megy
  a feladónak), a fotósnak címzett szálnál **a fotós nevében is**, ha ő épp nem elérhető. A fotós a
  saját `/photographer/messages` oldalán látja és megválaszolja a hozzá érkezett kérdéseket.
- **Események** — esemény + média CRUD (fotós is, csak a sajátját). A lista **szűrhető**
  szabad szöveggel (név / helyszín / ország), országra, fotósra és dátum-tartományra.
  Az esemény nevére / a „Szerkesztés" gombra kattintva **egyetlen oldal nyílik**: felül az
  esemény adatai (szerkesztés + törlés + **esemény-szintű árazás**: egy „Fotó ára" és egy
  „Videó ára" — nem fájlonként; módosításkor a még el nem adott médiák ára automatikusan átáll),
  alatta a képek/videók feltöltése, **„Beolvasás FTP-ről"** import (a távoli SFTP/NAS szerverre
  feltöltött teljes méretű képek mappánként böngészhetők, kijelölhetők, egy lépésben importálhatók)
  és a média-rács (ár kiírva; **kattintással vagy drag-kerettel több elem kijelölhető és egyszerre
  törölhető** — FTP-importált médiánál az eredeti forrás-fájl is törölhető a távoli szerverről;
  a törlés minden legyártott fájlt eltávolít: thumbnail, előnézet, sprite, HLS, letölthető változatok).
  **Duplikátum-szűrés**: azonos tartalmú fájl (SHA-256) egy eseménybe csak egyszer kerül be — sem
  feltöltéssel, sem FTP-importtal. Az FTP-import és az árazás már az új-esemény űrlapon is elérhető.
- **Hibanapló** (`/admin/errors`) — kezeletlen kivételek csoportosítva, lezárás/újranyitás.

### Beállítások (superadmin)
- **Kritikus beállítások** (`/admin/settings/critical`) — állapot-áttekintő (fizetés / tárhely / e-mail /
  ütemező / feldolgozás / **monitoring & mentés**), **fizetési kulcsok** (Stripe / SimplePay / Barion, titkosítva),
  **e-mail küldés** (SMTP host/port/titkosítás/jelszó — titkosítva —, feladó + válasz (Reply-To) cím, **„tesztlevél küldése"** gomb),
  **hCaptcha** (opcionális — bekapcsolva a Kapcsolat űrlapon a hCaptcha widget váltja a számtani kérdést),
  **számlázás** (Billingo), **monitoring** (hiba-webhook, e-mail értesítés, „mentés most"),
  **oldal-átnevezés** (Előnézet → Átnevezés: rendszer-e-mailek + beállítások átírása egy új domainre).
  → Minden kritikus külső hozzáférés a felületről állítható, nem kell a szerver `.env`-jét szerkeszteni.
- **SEO** (`/admin/settings/seo`) — alap meta-leírás, cím-kiegészítés, OG-kép feltöltés,
  Google Search Console azonosító, **globális „kereshetőség" kapcsoló** (indulás előtti mód).
- **GEO (AI-keresők)** (`/admin/settings/geo`) — a klasszikus SEO-n felül egy dinamikus **`/llms.txt`**:
  ember által olvasható oldaltérkép + leírás az AI-crawlereknek (ChatGPT, Perplexity, Gemini…).
  A rendszer automatikusan generálja (leírás + fő oldalak + GYIK + élő események); a leírás szerkeszthető,
  ki/be kapcsolható. Az oldalon részletes emlékeztető a működésről.
- **Jogi oldalak** (`/admin/settings/legal`) — az **Impresszum** és az **ÁSZF** Markdown-szerkesztője
  (a publikus `/impresszum` és `/aszf` oldalak tartalma). Vázakkal előtöltve `[kitöltendő]` jelölőkkel.
- **Közösségi média** (`/admin/settings/social`) — Facebook / Instagram / YouTube / TikTok URL-ek;
  a kitöltöttek megjelennek a publikus láblécben és a Kapcsolat oldalon.
- **Biztonság** (`/admin/settings/security`) — **kétfaktoros hitelesítés (2FA / TOTP)** minden adminnak
  a sajátjához (QR + helyreállító kódok); superadmin kötelezővé teheti mindenkinek.
- **Fotósok** (`/admin/photographers`) — CRUD + meghívó (48 órás token), profil, jutalék %, jelszó-visszaállítás.
- **GDPR kérelmek** (`/admin/data-requests`) — adat-export (JSON) / anonimizálás.
- **Kép-visszakövetés** (`/admin/forensics`) — minden megvásárolt letöltésbe láthatatlan, a rendeléshez
  kötött jel kerül (a letöltés pillanatában, memóriában — a tárolt fájl nem változik). Kiszivárgott képet
  feltöltve megmondja, melyik rendelésből / vásárlótól származik. Adminból ki/be kapcsolható.
- **Szervező kifizetések** (`/admin/organizer-payouts`) — az esemény-szervezőknek járó / kifizetett
  bevétel-részesedés kézi könyvelése.
- **Oldal neve / márkajel** (branding) — kétszínű szöveges logó **VAGY feltöltött kép** (SVG / PNG / WebP,
  fő + opcionális „sötét háttérre" változat); a kép megjelenik a fejlécben, láblécben, **e-mailekben** (PNG),
  és a **közösségi megosztóképen** (automatikusan generálva). Kép híján a szöveges logó marad.
- **Tárhely** (NAS/R2), **Vízjel** (élő előnézettel), **Hero média**,
  **Rendszámfelismerés** (Plate Recognizer, teljesen kikapcsolható), **E-mail sablonok** (10 sablon, 5-5 mező).
- **Téma** (`/admin/settings/theme`) — 6 színséma világos+sötét párban + testreszabható; **teljes animáció-vezérlés**:
  oldalváltás, hero-mozgás, kártya-hover (és külön a kártyán belüli kép hover-effektje), görgetés-reveal,
  statisztika-számlálók, fejléc-fátyolüveg, gomb-visszajelzés, kosárba-repülő kép, téma-váltás áttűnés,
  GY.I.K. lenyílás — mindegyik ki/be kapcsolható, a `prefers-reduced-motion` mindig felülír.
- **Éles ↔ helyi szinkron** (`/admin/settings/data-sync`) — az éles adatbázis + média egyirányú letöltése
  a fejlesztői gépre (biztonságos, „scrub"-olt másolat); a lap tetején magyarázat a dev↔éles munkafolyamatról.

---

## 6. Feldolgozó pipeline

1. Feltöltés → mindig a lokális `local` stagingre (valós fájl-útvonal kell a feldolgozáshoz).
2. **Kép** (`ProcessImageMedia`): rendszám-elemzés (ha be van kapcsolva) → 400×300 WebP thumbnail →
   1200px csempézett-vízjeles előnézet → letölthető JPEG 92% + WebP 90% eredeti felbontásban.
3. **Videó** (`ProcessVideoMedia`, külön `videos` queue): thumbnail → 720p vízjeles előnézet →
   scrub-sprite → HLS stream (adaptív, 2 minőség) → nem-MP4 remux.
4. Utána `ArchiveMediaOriginalToNas` → a nagy fájlokat az archív diskre (NAS / R2) mozgatja.
5. Sikertelen feldolgozás 3× retry után `status=failed`, a dashboard médiaegészség panel jelzi.

---

## 7. Automatizmusok (cron — `* * * * * php artisan schedule:run`)

| Gyakoriság | Parancs | Mit csinál |
|---|---|---|
| 5 perc | `roadsidephoto:heartbeat` | ütemező-életjel (a Kritikus beállítások ebből tudja, fut-e a cron) |
| 15 perc | `roadsidephoto:scan-alerts` | kritikus dashboard-figyelmeztetések → e-mail a superadminoknak |
| óránként | `roadsidephoto:send-download-reminders`, `…:send-abandoned-cart-reminders`, `…:purge-delivery-cache` | letöltési emlékeztető / elhagyott kosár / delivery-cache takarítás |
| naponta 03:15 | `roadsidephoto:backup` | **automatikus adatbázis-mentés** (`pg_dump` → gzip → beállított disk, 14 megtartva) |
| hét/hónap eleje | fotós riportok | heti/havi értékesítési összesítő e-mailben (CSV-vel) |

Queue worker is kell élesben: `php artisan queue:work --queue=videos,imports,default`

---

## 8. Biztonság

- **Bejelentkezés-védelem**: háromrétegű brute-force elleni védelem (e-mail+IP 5/15 perc,
  IP-nként 25/15 perc, fiók-szintű zár 10/15 perc bármely IP-ről). Felhasználó-enumeráció ellen
  egységes hibaüzenet.
- **Kétfaktoros hitelesítés (2FA)**: TOTP + helyreállító kódok + „megbízom ebben a gépben (30 nap)".
  Superadmin kötelezővé teheti minden adminnak.
- **Titkosított tárolás**: minden szolgáltató-kulcs (Stripe, SimplePay, Barion, Billingo, R2, Plate Recognizer,
  hiba-webhook), a 2FA-titkok és a helyreállító kódok Crypt-titkosítva a `site_settings` / `users` táblában.
- **GDPR**: publikus adatkiadási/törlési kérelem folyamat + admin oldali export/anonimizálás.
- **Cookie consent** banner (jelenleg csak működéshez szükséges cookie).
- **Hibakövetés**: minden kezeletlen kivétel rögzül a hibanaplóba + opcionális e-mail/webhook értesítés.

---

## 9. Tesztek + CI

- **571 PHPUnit teszt** (Feature + Unit), PostgreSQL tesztadatbázison (a 2 PostGIS-es GPS-teszt CI-ben kimarad).
- **Laravel Pint** stíluscheck.
- **GitHub Actions** (`.github/workflows/ci.yml`, `github.com/bzoli82/roadsidephoto`, `main`): minden push/PR-re
  párhuzamosan fut PHPUnit (`postgres:16-alpine`, sima Postgres, mint az éles cél + `apt-get install ffmpeg`) + Pint + `npm run build`.
  **A CI zöld.**

---

## 10. Mi van még hátra az éles indulásig?

### Jogi tartalom (a kód kész, a szöveg az üzemeltetőtől jön)
- **Impresszum + ÁSZF** — az `/admin/settings/legal` oldalon a vázakat ki kell tölteni valós
  cégadatokkal / ügyvéddel véglegesített ÁSZF-fel. A `/privacy` (adatvédelmi) szöveget is át kell nézni.
- **Számlázás ÁFA/OSS** — a Billingo ÁFA-jelölést a könyvelővel kell egyeztetni.

### Konfiguráció (a kód kész, csak be kell tölteni)
- **Éles kulcsok**: Stripe (élő), SimplePay (éles merchant), Barion (éles POSKey), Billingo API-kulcs + számlatömb,
  Cloudflare R2 (2 bucket + kulcsok + publikus domain), valódi mail-szolgáltató (SMTP — adminból is).
- **Szerver**: PostgreSQL + PostGIS, `queue:work` + `schedule:run` (cron), `php artisan storage:link`,
  `pg_dump` elérhető (mentéshez), `APP_KEY` / `APP_URL` (https) / `APP_DEBUG=false`.
- **Webszerver**: hosszú `Cache-Control` a `/build/*` és a média-fájlokra.
- Részletek: **ELES-INDULAS-CHECKLIST.md**.

### Az átnevezés (kanyarfotozas → roadsidephoto.eu) — KÉSZ (2026-09-09)
- Minden nem vizuális objektum át van írva: rendszer-e-mailek (`@roadsidephoto.eu`), `site_settings`,
  artisan parancsnévtér (`roadsidephoto:*`), NAS/R2 defaultok, localStorage-kulcsok (`roadsidephoto.*`),
  tesztadatbázis (`roadsidephoto` / `roadsidephoto_test`), git repo (`bzoli82/roadsidephoto`), `docs/`, `CLAUDE.md`.
- **Admin funkció**: `/admin/settings/critical` → „Az oldal neve / átnevezés" — bármikor
  ÚJABB domainre átnevezhető (Előnézet + Átnevezés). CLI: `php artisan roadsidephoto:apply-identity <domain>`.
- Ellenőrzés: `php artisan roadsidephoto:audit-identity` → tiszta.
- Éles indulásnál a DB neve + `.env` a szerveren manuálisan igazítandó, ha a helyitől eltérő domaint használsz (az előnézet kiírja).

### Nem blokkoló, ajánlott
- Deploy-runbook (részletes telepítési dokumentáció).
- Teljesítmény: Lighthouse-finomhangolás (az alapok — focus-visible, kontraszt, lazy-load, skip-link — kész).

---

## 11. Jövőbeli ötletek (elhalasztva — bevétel-növelő funkciók)

Nem része a jelenlegi körnek, a tulajdonos szándékosan későbbre tette:

1. **„Találd meg magad" — rajtszám / arc alapú keresés.** A vásárló beírja a rajtszámát,
   és megkapja az összes róla készült képet. A meglévő rendszámfelismerő (Plate Recognizer)
   infrastruktúrára építhető (OCR a rajtszám-táblán / arcfelismerés). Motorsportban ez a
   legnagyobb konverzió-növelő — a vásárló nem tud több száz kép közt végignézni.
2. **„Minden kép rólam" csomagár.** Egy kattintással a vásárló összes (rajtszám/arc alapján
   hozzárendelt) képét a kosárba teszi csomag-kedvezménnyel. Jól illeszkedik a kész
   esemény-szintű árazáshoz.
3. **Print-on-demand (poszter / vászonkép).** Külső nyomdai partner a fizetési absztrakció
   mögött, extra árrés; a vásárló letöltés helyett/mellett fizikai terméket is rendelhet.

---

## 12. Belépési adatok

A demo/fejlesztői fiókok jelszavait külön fájl tartalmazza: **belepesi-adatok.txt**
(éles indulás előtt kötelezően cserélendő).
