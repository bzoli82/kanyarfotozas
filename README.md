# RoadsidePhoto

Magyar motorsport **fotó- és videó-értékesítő platform**. A fotósok pályanapokon, versenyeken,
találkozókon rögzítik a résztvevőket; a résztvevők helyszín, dátum és időpont szerint megkeresik a
magukról készült felvételeket, és **regisztráció nélkül**, csak e-mail-címmel megvásárolják, vízjel
nélkül letöltik.

- **Márkanév:** RoadsidePhoto · **domain:** `roadsidephoto.eu`
- **Állapot:** minden tervezett funkció kész, 571 automata teszt zöld, CI zöld (`github.com/bzoli82/roadsidephoto`)

## Tech stack

| Réteg | Megoldás |
|---|---|
| Backend | Laravel 13 (PHP 8.4), session-alapú auth, Spatie Permission + ActivityLog |
| Frontend | Vue 3 + Inertia.js (SPA), Tailwind CSS 4, Leaflet |
| Adatbázis | PostgreSQL 18 (+ PostGIS opcionális, csak a GPS sugaras kereséshez) |
| Kép | Intervention Image (GD) — WebP thumbnail, csempézett vízjel, rendszám-homályosítás |
| Videó | FFmpeg (opcionális — `preprocessed` módban a szervernek nem kell) |
| Tárhely | fejlesztésben lokális disk; élesben Cloudflare R2 (env-vezérelt, kód nem változik) |
| Fizetés | Stripe + SimplePay v2 + Barion Smart Gateway v2 (adminból ki/be) |
| Számlázás | Billingo v3 |

## Fejlesztői indítás

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# PostgreSQL adatbázis: roadsidephoto / roadsidephoto_test
php artisan migrate --seed          # DemoDataSeeder: demó események + placeholder média
npm run dev                          # + php artisan serve
```

Dev belépés: `superadmin@roadsidephoto.eu` / `password` (éles indulás előtt cserélendő).

## Tesztek

```bash
php artisan test          # 571 teszt, PostgreSQL tesztadatbázison
vendor/bin/pint           # kód-stílus
```

## Dokumentáció

| Fájl | Tartalom |
|---|---|
| `PROJEKT-OSSZEFOGLALO.md` | Teljes funkció-áttekintés (publikus + admin felület, pipeline, biztonság) |
| `ELES-INDULAS-CHECKLIST.md` | Éles indulás — környezet + konfiguráció pipálós lista |
| `docs/DEPLOY-HETZNER-COOLIFY.md` | Lépésről lépésre szerver-telepítés (Hetzner CX22 + Coolify + R2) |
| `CLAUDE.md` | Fejlesztői/architektúra-jegyzetek, buktatók, döntések (AI-agent + ember) |

## Éles ↔ fejlesztői szinkron

A **kód** egy irányba folyik (fejlesztői → éles: `git push` → automatikus deploy → `php artisan migrate`),
az **adat** a másikba (éles → fejlesztői: `/admin/settings/data-sync`). Élesen soha ne szerkessz kódot,
és ne futtass `migrate:fresh`-t vagy `DemoDataSeeder`-t. Részletek a data-sync admin oldal tetején és a
`PROJEKT-OSSZEFOGLALO.md`-ban.
