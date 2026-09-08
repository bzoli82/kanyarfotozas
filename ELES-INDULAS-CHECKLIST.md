# Éles indulás — checklist

A kód minden pontban kész; ez a lista a **környezet + konfiguráció** teendői.
A rendszer állapotát a `/admin/settings/critical` oldal élőben mutatja (zöld/sárga/piros).

---

## 1. Szerver / infrastruktúra

- [ ] PHP 8.4 (`pdo_pgsql`, `pgsql`, `gd`, `exif`, `intl`, `zip`, `bcmath`, `mbstring`)
- [ ] PostgreSQL 18 + **PostGIS** kiterjesztés az éles adatbázisban (`CREATE EXTENSION postgis;`)
- [ ] FFmpeg + FFprobe telepítve (videófeldolgozás + mentés) — `FFMPEG_BINARY` / `FFPROBE_BINARY` a `.env`-ben ha nincs a PATH-on
- [ ] `pg_dump` elérhető (napi mentés) — `PG_DUMP_BINARY` ha nincs a PATH-on
- [ ] `php artisan storage:link` lefuttatva
- [ ] **Queue worker**: `php artisan queue:work --queue=videos,default` (supervisor / systemd)
- [ ] **Cron**: `* * * * * php artisan schedule:run`
- [ ] Webszerver: hosszú `Cache-Control` a `/build/*` és a média-fájlokra (nginx/apache)

## 2. `.env` — alaprendszer

- [ ] `APP_KEY` generálva (`php artisan key:generate`)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL=https://<végleges-domain>` (HTTPS — a fizetési visszatérési URL-ek ebből képződnek)
- [ ] `APP_NAME` + `DB_DATABASE` a végleges névre (ld. 7. pont)
- [ ] `MAIL_MAILER=smtp` + valódi SMTP adatok, `MAIL_FROM_ADDRESS`
- [ ] `QUEUE_CONNECTION=database` (vagy redis), `CACHE_STORE=database` (vagy redis)

## 3. Tárhely — Cloudflare R2

A kulcsokat lehet `.env`-ből VAGY a `/admin/settings/storage` oldalról megadni.

- [ ] 2 bucket: `r2_public` (publikus, CDN mögött) + `r2_private` (privát)
- [ ] `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`
- [ ] `R2_PUBLIC_BUCKET`, `R2_PRIVATE_BUCKET`, `R2_PUBLIC_URL` (a publikus bucket saját domainje / CDN)
- [ ] `MEDIA_PUBLIC_DISK=r2_public`, `MEDIA_ARCHIVE_DISK=r2_private`
- [ ] A `.ts` HLS-szegmensekre érdemes Cloudflare CDN cache

## 4. Fizetés (`/admin/settings/critical` → titkosítva tárolva)

- [ ] **Stripe** élő: secret key, publishable key, **webhook secret**
      (webhook endpoint a Stripe Dashboardban: `{APP_URL}/api/stripe/webhook`)
- [ ] **SimplePay** éles: merchant azonosító, secret key, SANDBOX = KI
      (IPN URL a SimplePay adminban: `{APP_URL}/api/simplepay/ipn`)
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

Amint eldőlt a név (pl. `kanyarfotozas.hu`):

1. `/admin/settings/critical` → „Az oldal végleges domainje" mezőbe beírni
2. Terminálból:
   ```
   php artisan kanyarfotozas:apply-identity kanyarfotozas.hu --dry-run   # próba
   php artisan kanyarfotozas:apply-identity kanyarfotozas.hu             # végrehajtás
   ```
3. Kézi teendők (a parancs kiírja): `.env` `DB_DATABASE` / `APP_NAME` / `APP_URL` / `MAIL_FROM_ADDRESS`,
   adatbázis átnevezése, `php artisan config:clear`, worker újraindítás
4. `php artisan kanyarfotozas:audit-identity` — ellenőrzés, maradt-e régi „kanyarfoto" nyom
5. Fejlesztői: a `kanyarfotozas:*` parancsnévtér átírása (nem user-facing)

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
