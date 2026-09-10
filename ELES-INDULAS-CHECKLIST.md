# Éles indulás — checklist

A kód minden pontban kész; ez a lista a **környezet + konfiguráció** teendői.
A rendszer állapotát a `/admin/settings/critical` oldal élőben mutatja (zöld/sárga/piros).

A lépésről lépésre szóló szerver-telepítés (Hetzner CX22 + Coolify): **`docs/DEPLOY-HETZNER-COOLIFY.md`**.

---

## 1. Szerver / infrastruktúra

- [ ] PHP 8.4 (`pdo_pgsql`, `pgsql`, `gd`, `exif`, `intl`, `zip`, `bcmath`, `mbstring`)
- [ ] PostgreSQL 16+ (a Coolify sablon 16-ot ad) — **PostGIS NEM kell** (a GPS sugaras keresés alapból KI, ld. `/admin/settings/location-search`)
- [ ] **FFmpeg NEM kell** — a videó-mód `preprocessed` (`MEDIA_VIDEO_MODE=preprocessed`): a fotós kódolja a kis felbontású előnézetet, a szerver nem
- [ ] `pg_dump` elérhető (napi mentés) — `PG_DUMP_BINARY` ha nincs a PATH-on (a `postgresql-client` csomag adja)
- [ ] `php artisan storage:link` lefuttatva
- [ ] **Queue worker**: `php artisan queue:work --queue=videos,imports,default` (Coolify: külön „worker" process; nagy importhoz 2-3 replika)
- [ ] **Cron**: `* * * * * php artisan schedule:run` (Coolify: „scheduled task" vagy cron-process)
- [ ] Perzisztens kötet a `storage/app` alá (staging + delivery cache — NEM efemer!)
- [ ] Webszerver: hosszú `Cache-Control` a `/build/*` és (ha lokális disk) a média-fájlokra

> **Ha később mégis kell** a GPS sugaras keresés: `apt install postgresql-16-postgis-3`,
> `CREATE EXTENSION postgis;`, a GIST index kézzel (ld. `create_events_table` migráció
> kommentje), majd `/admin/settings/location-search` → bekapcsol.
> Szerver-oldali videókódolás: `apt install ffmpeg`, `MEDIA_VIDEO_MODE=pipeline`.

## 2. `.env` — alaprendszer

- [ ] `APP_KEY` generálva (`php artisan key:generate`)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL=https://<végleges-domain>` (HTTPS — a fizetési visszatérési URL-ek ebből képződnek)
- [ ] `APP_NAME` + `DB_DATABASE` a végleges névre (ld. 7. pont)
- [ ] `MAIL_MAILER=smtp` + valódi SMTP adatok, `MAIL_FROM_ADDRESS` (VAGY a `/admin/settings/critical` → E-mail szekcióból)
- [ ] `QUEUE_CONNECTION=database` (vagy redis), `CACHE_STORE=database` (vagy redis)
- [ ] `MEDIA_VIDEO_MODE=preprocessed`

## 3. Tárhely — Cloudflare R2

A kulcsokat lehet `.env`-ből VAGY a `/admin/settings/storage` oldalról megadni.

- [ ] 2 bucket: `r2_public` (publikus, CDN mögött) + `r2_private` (privát)
- [ ] `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`
- [ ] `R2_PUBLIC_BUCKET`, `R2_PRIVATE_BUCKET`, `R2_PUBLIC_URL` (a publikus bucket saját domainje / CDN)
- [ ] `MEDIA_PUBLIC_DISK=r2_public`, `MEDIA_ARCHIVE_DISK=r2_private`
- [ ] (Nagy feltöltéshez) `R2_IMPORT_BUCKET` + `MEDIA_IMPORT_DISK=r2_import` + **CORS-szabály** az import bucketen (PUT az app origin-jéről) — ld. deploy runbook „Nagy feltöltés" szakasz
- [ ] A `.ts` HLS-szegmensekre érdemes Cloudflare CDN cache

## 4. Fizetés (`/admin/settings/critical` → titkosítva tárolva)

- [ ] **Stripe** élő: secret key, publishable key, **webhook secret**
      (webhook endpoint a Stripe Dashboardban: `{APP_URL}/api/stripe/webhook`)
- [ ] **SimplePay** éles: merchant azonosító, secret key, SANDBOX = KI
      (IPN URL a SimplePay adminban: `{APP_URL}/api/simplepay/ipn`)
- [ ] **Barion** éles: POSKey, a fiók e-mailje (payee), SANDBOX = KI
      (callback URL a Barion shopnál: `{APP_URL}/api/barion/callback`)
- [ ] Alapértelmezett szolgáltató kiválasztva; a nem használt szolgáltató „Elérhető a pénztárban" = KI
- [ ] Alap médiaár beállítva (`/admin/settings` vagy `site_settings.base_price_huf`)

## 5. Számlázás (magyar webshopnál kötelező!)

- [ ] **Billingo** v3 API-kulcs + számlatömb (block) azonosító a `/admin/settings/critical` → Számlázás szekcióban
- [ ] ÁFA-jelölés a könyvelővel egyeztetve (alap: `AAM` — alanyi adómentes)
- [ ] „Automatikus számla a sikeres fizetés után" bekapcsolva
- [ ] Vállalkozási forma tisztázva (EV átalányadó / cég) — a rendszer névre szóló számlát állít ki

## 6. Biztonság

- [ ] Superadmin + admin fiókokon **2FA bekapcsolva** (`/admin/settings/security`)
- [ ] Superadmin: „Kötelező 2FA minden adminnak" bekapcsolva
- [ ] **Minden seedelt demo-jelszó lecserélve** (ld. belepesi-adatok.txt)
- [ ] SEO → „kereshetőség" kapcsoló: indulásig **KI** (karbantartási mód), éleskor BE
- [ ] Hiba-értesítés: webhook (Slack/Discord) VAGY e-mail bekapcsolva

## 7. Végleges domain

A név **eldőlt: `roadsidephoto.eu`** — az átnevezés kód/DB/artisan-névtér szinten KÉSZ (`roadsidephoto:*`).
Ezt a pontot csak akkor kell újra végigcsinálni, ha **másik domainre** váltanál:

1. `/admin/settings/critical` → „Az oldal végleges domainje" mezőbe beírni az új domaint
2. Terminálból:
   ```
   php artisan roadsidephoto:apply-identity <uj-domain> --dry-run   # próba
   php artisan roadsidephoto:apply-identity <uj-domain>             # végrehajtás
   ```
3. Kézi teendők (a parancs kiírja): `.env` `DB_DATABASE` / `APP_NAME` / `APP_URL` / `MAIL_FROM_ADDRESS`,
   adatbázis átnevezése, `php artisan config:clear`, worker újraindítás
4. `php artisan roadsidephoto:audit-identity` — ellenőrzés, maradt-e régi „kanyarfotozas" nyom (tisztának kell lennie)

> **Éles indulásnál** (nem domain-váltás): elég a szerver `.env`-jét a `roadsidephoto.eu`-ra igazítani
> (`APP_URL=https://roadsidephoto.eu`, `DB_DATABASE=roadsidephoto`, `APP_NAME=RoadsidePhoto`,
> `MAIL_FROM_ADDRESS=noreply@roadsidephoto.eu`), a többi már a kódban van.

## 8. DNS / e-mail deliverability

- [ ] A domain DNS: A/AAAA rekord a szerverre
- [ ] E-mail: **SPF + DKIM + DMARC** rekord a küldő domainre (különben spam-be megy a visszaigazoló)
- [ ] SSL tanúsítvány (Let's Encrypt)

## 9. Indulás után

- [ ] `/admin/settings/critical` — minden csoport **zöld**
- [ ] Teszt-vásárlás végigvitele (kis összeg) mindkét fizetési szolgáltatóval
- [ ] Számla megérkezik + a NAV Online Számlában látszik
- [ ] Első napi mentés lefutott (`/admin/settings/critical` → Monitoring)
- [ ] `sitemap.xml` beküldve a Google Search Console-ba

## 10. Élesítés után — új funkció fejlesztése

Amikor már élesen töltöd a tartalmat, és új funkciót akarsz fejleszteni:
**a kód fejlesztői → éles (`git push` → auto-deploy → `migrate`), az adat éles → fejlesztői
(`/admin/settings/data-sync`).** Élesen SOHA ne szerkessz kódot / ne futtass `migrate:fresh`-t.
A teljes munkafolyamat: `docs/DEPLOY-HETZNER-COOLIFY.md` → „15. Fejlesztés élesítés után",
és röviden a `/admin/settings/data-sync` oldal tetején.
