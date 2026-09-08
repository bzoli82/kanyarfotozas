# Deploy runbook — Hetzner CX22 + Coolify

Cél: a KanyarFotózás platform éles üzembe helyezése a **legolcsóbb, ehhez az apphoz
illő** módon.

- **Szerver**: Hetzner Cloud **CX22** (2 vCPU / 4 GB RAM / 40 GB SSD) — ~€4,5/hó.
  (Alternatíva: **CAX11** ARM, 4 GB, ~€3,8/hó — minden függőség fut ARM-on. A lépések azonosak.)
- **Vezérlőpanel**: **Coolify** (ingyenes, self-hosted PaaS — deploy, SSL, env, worker).
- **Tárhely**: Cloudflare **R2** (ehhez a forgalomhoz gyakorlatilag ingyen, nincs egress-díj).
- **Adatbázis**: PostgreSQL 16, a Coolify sablonból. **PostGIS NEM kell.**
- **FFmpeg NEM kell** — a videó-mód `preprocessed`.

Becsült teljes havidíj: **~€4–5 + a domain**.

> A `/admin/settings/critical` oldal éles állapotot mutat (zöld/sárga/piros). A
> `ELES-INDULAS-CHECKLIST.md` a magas szintű pipálós lista; ez a fájl a
> lépésről lépésre szóló szerver-setup.

> **Ha a tartalmat (események, oldalszövegek, GYIK, árak, fotósok, képek) HELYBEN
> töltöd fel és 1:1-ben akarod élesre vinni** — úgy, hogy amit a gépeden látsz,
> azt lásd élesen is —: ugord át előbb a **„Tartalom helyben → élesre"**
> szakaszt (a Hibaelhárítás előtt), az megmondja a sorrendet (APP_KEY rögzítése,
> R2-re állás, adatbázis-átvitel).

---

## 0. Előfeltételek (a gépeden)

- Egy SSH-kulcs (`~/.ssh/id_ed25519.pub`). Ha nincs: `ssh-keygen -t ed25519`.
- Hozzáférés a domain DNS-éhez (pl. `kanyarfotozas.hu`).
- Cloudflare-fiók (R2-höz).
- A GitHub repo: `github.com/bzoli82/kanyarfotozas` (privát is jó, Coolify deploy-kulccsal fér hozzá).

---

## 1. Hetzner szerver létrehozása

1. Hetzner Cloud Console → **New Project** → „kanyarfotozas".
2. **Add Server**:
   - Location: **Nürnberg** vagy **Falkenstein** (közel HU-hoz, alacsony latency).
   - Image: **Ubuntu 24.04**.
   - Type: **CX22** (Shared vCPU, x86) — vagy **CAX11** (Ampere ARM).
   - **SSH key**: add hozzá a publikus kulcsod.
   - Name: `kf-prod-1`.
   - **Create & Buy now**.
3. Jegyezd fel a szerver **publikus IPv4** címét.
4. (Ajánlott) Hetzner **Firewall** a projektben: engedélyezd befelé a **22** (SSH),
   **80** (HTTP), **443** (HTTPS), **8000** (Coolify UI — később lekapcsolható) portokat,
   minden mást tilts. Rendeld a szerverhez.

---

## 2. Alap szerver-beállítás

SSH-zz be: `ssh root@<SZERVER_IP>`

```bash
# rendszerfrissítés
apt update && apt upgrade -y

# időzóna
timedatecfg set-timezone Europe/Budapest   # ha nincs: timedatectl set-timezone Europe/Budapest

# 2 GB swap (a CX22-n a képfeldolgozás-csúcsokhoz biztonsági tartalék)
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# postgresql-client (a pg_dump kell a napi mentéshez)
apt install -y postgresql-client

# alap tűzfal (ha nem a Hetzner Firewallt használod)
apt install -y ufw
ufw allow 22 && ufw allow 80 && ufw allow 443 && ufw allow 8000
ufw --force enable
```

---

## 3. Coolify telepítése

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

- ~3–5 perc. A végén kiírja: `http://<SZERVER_IP>:8000`.
- Nyisd meg böngészőben → **regisztráld az első (admin) fiókot** azonnal
  (az első regisztráció lesz a tulajdonos; utána a nyilvános regisztráció zárható).
- Settings → **Instance Settings**:
  - állítsd be az „Instance Domain"-t később a Coolify saját domainjéhez (opcionális),
  - kapcsold ki a public registrationt.

---

## 4. Cloudflare R2 (tárhely)

1. Cloudflare Dashboard → **R2** → **Create bucket**: `kanyarfotozas-public`.
2. Még egy: `kanyarfotozas-private`.
3. (Opcionális, a tömeges importhoz) még egy: `kanyarfotozas-import` — ide
   rclone-nal / S3-klienssel töltöd fel a nagy fotó-mappákat, az admin
   eseményhez importálja. Ld. „Tömeges import (5000+ kép)" szakasz.
4. A **public** bucket → Settings → **Public access**: engedélyezd az `r2.dev`
   aldomént, VAGY (ajánlott) köss rá egy **custom domaint** (pl. `media.kanyarfotozas.hu`)
   Cloudflare CDN mögött.
5. R2 → **Manage API Tokens** → **Create API Token**:
   - Permissions: **Object Read & Write**,
   - Bucket: mindegyik (vagy „Apply to all buckets"),
   - jegyezd fel: **Access Key ID**, **Secret Access Key**, és az
     **S3 API endpoint**-ot (`https://<accountid>.r2.cloudflarestorage.com`).

> A kulcsokat megadhatod az env-ben (lentebb) **VAGY** az admin
> `/admin/settings/storage` → „Cloudflare R2" szekcióban (titkosítva a
> `site_settings`-ben, kapcsolat-teszt gombbal). A disk-szerepet
> (`MEDIA_PUBLIC_DISK` / `_ARCHIVE_DISK` / `_IMPORT_DISK`) mindig az env dönti el.

---

## 5. Coolify: projekt + PostgreSQL

1. Coolify → **Projects** → **+ Add** → „kanyarfotozas" → **Environment: production**.
2. A projektben → **+ New Resource** → **Databases** → **PostgreSQL 16**.
   - Név: `kf-postgres`.
   - Jegyezd fel a Coolify által generált jelszót és a belső hostnevet
     (pl. `kf-postgres`), portot (`5432`), db-nevet, usert.
   - **Deploy**.
   - A DB alapból csak a Coolify belső hálózatán érhető el (jó — ne tedd publikussá).

---

## 6. Coolify: az alkalmazás

1. A projektben → **+ New Resource** → **Application** → **Public Repository** vagy
   **GitHub App** (privát repóhoz a GitHub App a kényelmesebb; egyszeri repo-jogosítás).
   - Repository: `https://github.com/bzoli82/kanyarfotozas`
   - Branch: `main`
   - Build Pack: **Nixpacks** (Laravelt felismeri: PHP 8.4 + `composer install` +
     `npm ci && npm run build`).
2. **Általános beállítások**:
   - Ports Exposes: `8080` (a Nixpacks Laravel a beépített szerverét ezen adja) —
     Coolify ezt automatikusan proxyzza; ha nem, állítsd `80`-ra a Nixpacks configból.
   - Health check path: `/` (vagy `/login`).
3. **Build**:
   - Install Command: (üresen hagyva a Nixpacks intézi) — ha kézzel kell:
     `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
   - Start Command:
     `php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan storage:link || true && php artisan serve --host=0.0.0.0 --port=8080`
     > Éles alternatíva a `php artisan serve` helyett: `php-fpm` + `nginx` (Dockerfile-alapú
     > deploy). Kezdésnek a `serve` is jó egy CX22-n, később válts.

### 6/a. Perzisztens kötet (KÖTELEZŐ)

A `storage/app` **nem lehet efemer** — itt van a feltöltés-staging és a
**delivery cache** (a megvásárolt fájlok gyors másolata).

- Application → **Storages** → **+ Add**:
  - Type: **Volume**
  - Name: `kf-storage`
  - Mount Path: `/app/storage/app`
  - (a `storage/logs` és `framework/cache` maradhat efemer, vagy tedd az egész
    `/app/storage`-ot kötetre — a `views`/`sessions` regenerálódik.)

### 6/b. Környezeti változók

Application → **Environment Variables** → illeszd be (a `<...>` helyekre a valós értéket):

```dotenv
APP_NAME="KanyarFotózás"
APP_ENV=production
APP_KEY=                         # 6/c-ben generáljuk
APP_DEBUG=false
APP_URL=https://kanyarfotozas.hu
APP_LOCALE=hu
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=kf-postgres              # a Coolify DB belső hostneve
DB_PORT=5432
DB_DATABASE=<coolify_db_neve>
DB_USERNAME=<coolify_db_user>
DB_PASSWORD=<coolify_db_jelszo>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# --- Média / videó ---
MEDIA_VIDEO_MODE=preprocessed
MEDIA_PUBLIC_DISK=r2_public
MEDIA_ARCHIVE_DISK=r2_private
MEDIA_DELIVERY_DISK=delivery
MEDIA_DELIVERY_MAX_AGE_HOURS=168
MEDIA_IMPORT_DISK=r2_import          # tömeges import az „import" bucketből (vagy `nas`)

# --- Cloudflare R2 ---
R2_ACCESS_KEY_ID=<...>
R2_SECRET_ACCESS_KEY=<...>
R2_DEFAULT_REGION=auto
R2_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com
R2_PUBLIC_BUCKET=kanyarfotozas-public
R2_PRIVATE_BUCKET=kanyarfotozas-private
R2_IMPORT_BUCKET=kanyarfotozas-import
R2_PUBLIC_URL=https://media.kanyarfotozas.hu

# --- Mentés ---
PG_DUMP_BINARY=pg_dump
BACKUP_DISK=r2_private
BACKUP_PATH=backups
BACKUP_KEEP=14

# --- E-mail (VAGY az adminból: /admin/settings/critical) ---
MAIL_MAILER=smtp
MAIL_HOST=<smtp_host>
MAIL_PORT=587
MAIL_USERNAME=<...>
MAIL_PASSWORD=<...>
MAIL_FROM_ADDRESS=noreply@kanyarfotozas.hu
MAIL_FROM_NAME="KanyarFotózás"

# A fizetési / számlázási kulcsokat NE ide — a /admin/settings/critical
# oldalról add meg (titkosítva a site_settings-ben).
```

> **Nem kell**: `FFMPEG_BINARY`, `FFPROBE_BINARY`, PostGIS, `NAS_*` (az R2 a tár).

### 6/c. APP_KEY

Első deploy előtt a Coolify **Terminal**-jában (vagy egy egyszeri parancsban):

```bash
php artisan key:generate --show
```

A kapott `base64:...` értéket másold az `APP_KEY` env-be. **Ezt soha ne cseréld
később** (a titkosított `site_settings` mezők — fizetési kulcsok, 2FA-titkok —
ezzel vannak titkosítva).

---

## 7. Worker + Scheduler

A Coolify **Application** → **+ Add** (Compose-alapú resource) VAGY külön
„Service" a queue workerhez és a schedulerhez. A két folyamat:

**Queue worker** (mindig fut):
```bash
php artisan queue:work --queue=videos,imports,default --sleep=3 --tries=3 --max-time=3600
```
> A `imports` queue-n a tömeges tárolóból-import batch-chunkjai futnak (lásd lent).
> Nagy import alatt érdemes 2 workert futtatni (a Coolify-ban a process replikák számát növelve).

**Scheduler** (percenként):
- Coolify → Application → **Scheduled Tasks** → **+ Add**:
  - Command: `php artisan schedule:run`
  - Frequency: `* * * * *`
- (Ha a Coolify-verziód nem ad Scheduled Tasks-ot: egy külön process
  `while true; do php artisan schedule:run; sleep 60; done`.)

A schedulerre 8 parancs épül (heartbeat, napi mentés, letöltés-emlékeztetők,
riasztás-scan, delivery-cache takarítás, fotós riportok, order-fulfillment retry).

---

## 8. Domain + SSL

1. **DNS** (a domain szolgáltatójánál / Cloudflare-nél):
   - `A` rekord: `kanyarfotozas.hu` → `<SZERVER_IP>`
   - `A` rekord: `www` → `<SZERVER_IP>` (opcionális, redirect)
   - `CNAME`/`A`: `media` → a Cloudflare R2 custom domain (ld. 4.3)
   - Ha Cloudflare-t használsz proxy-nak: a `kanyarfotozas.hu` rekord lehet
     „DNS only" (szürke felhő) az első Let's Encrypt-kiállításig, utána
     visszakapcsolható proxyra.
2. Coolify → Application → **Domains**: `https://kanyarfotozas.hu`
   - Coolify automatikusan kér **Let's Encrypt** tanúsítványt.
3. Deploy után ellenőrizd: `https://kanyarfotozas.hu` → betölt, lakat zöld.

---

## 9. Első deploy

1. Coolify → Application → **Deploy**.
2. Nézd a **build logot**: `composer install` → `npm run build` → konténer indul.
3. A start command lefuttatja a `migrate --force`-ot — az `events` migráció
   PostGIS híján **sima b-tree indexet** csinál (ez a várt viselkedés).
4. Ellenőrzés a Coolify **Terminal**-ból:
   ```bash
   php artisan about                     # env=production, debug=false
   php artisan migrate:status            # minden Ran
   php artisan storage:link              # ha a start commandban „|| true" elnyomta
   ```

---

## 10. Első superadmin + alapadatok

A Coolify Terminalból:

```bash
# Szerep-jogosultságok (ha a deploy nem seedelte):
php artisan db:seed --class=RolePermissionSeeder --force

# Első superadmin (tinker):
php artisan tinker --execute "\$u = App\Models\User::create(['name'=>'Zoli','email'=>'bzoli82@gmail.com','password'=>bcrypt('<ERŐS_JELSZÓ>'),'role'=>'superadmin','is_active'=>true]); echo \$u->id;"
```

> **NE** a demo `password123`-mal. A `belepesi-adatok.txt` fejlesztői fájl —
> élesre nem kerül (gitignore-olt).

Belépés: `https://kanyarfotozas.hu/login` → `/admin/settings/security` → **2FA be**.

---

## 11. Admin-oldali konfiguráció (`/admin/settings/critical`)

Sorban, amíg minden csoport **zöld**:

1. **Fizetés** — Stripe / SimplePay / Barion éles kulcsok (SANDBOX = KI),
   webhook/IPN/callback URL-ek beállítva a szolgáltatók oldalán:
   - Stripe: `https://kanyarfotozas.hu/api/stripe/webhook`
   - SimplePay: `https://kanyarfotozas.hu/api/simplepay/ipn`
   - Barion: `https://kanyarfotozas.hu/api/barion/callback`
2. **Számlázás** — Billingo v3 kulcs + számlatömb-azonosító, auto-számla BE.
3. **E-mail** — ha nem az env-ből: SMTP itt; küldj tesztlevelet.
4. **Monitoring** — hiba-webhook (Slack/Discord) vagy e-mail BE.
5. **Tárhely** (`/admin/settings/storage`) — az R2 kulcsok itt is megadhatók
   (ha nem env-ből); a státusz zöld.
6. **Alaprendszer** — `APP_DEBUG=false`, `APP_URL` https, alapár beállítva.
7. **SEO** (`/admin/settings/seo`) — a „kereshetőség" kapcsoló **KI**, amíg
   nem élesedsz igazán (karbantartási mód: minden oldal `noindex`,
   `robots.txt` `Disallow: /`). Éleskor BE.
8. **Helyszín-keresés** (`/admin/settings/location-search`) — marad **KI**
   (nincs PostGIS). A többi kereső (helyszínnév/ország/dátum/fotós/típus) + a
   térkép megy.

---

## 12. E-mail deliverability (KRITIKUS)

A visszaigazoló e-mailek spam-be esnek SPF/DKIM/DMARC nélkül. A küldő
domainre (`kanyarfotozas.hu`):

- **SPF** TXT: `v=spf1 include:<smtp_szolgáltató_spf> -all`
- **DKIM**: a szolgáltatónál generált CNAME/TXT rekord(ok)
- **DMARC** TXT (`_dmarc`): `v=DMARC1; p=quarantine; rua=mailto:dmarc@kanyarfotozas.hu`

Teszt: küldj magadnak egy tesztlevelet a `/admin/settings/critical` gombbal,
nézd meg a fejlécben `spf=pass` / `dkim=pass`.

---

## 13. Füst-teszt (éles, valódi pénz — kis összeg)

1. Hozz létre egy teszteseményt + tölts fel 1 fotót (a feldolgozás:
   `status=processing` → pár mp múlva `ready`; a worker fut).
2. Állíts az eseményen 100–200 Ft árat.
3. Nyisd inkognitóban → tedd kosárba → fogadd el az ÁSZF-et → fizess
   **mindhárom** szolgáltatóval (amit használni fogsz).
4. Ellenőrizd: visszaigazoló e-mail megjött, letöltés működik, **számla**
   kiállt és látszik a NAV Online Számla felületén.
5. Csinálj egy **részleges és egy teljes visszatérítést** az admin
   rendelés-nézetből — a teljesnél a letöltő token lejár + sztornó számla.
6. Másnap: `/admin/settings/critical` → Monitoring → **az első napi mentés
   lefutott** (a mentés az R2 `r2_private` bucketbe megy).
7. `sitemap.xml` beküldése a Google Search Console-ba, a „kereshetőség"
   kapcsoló BE.

---

## 14. Bővítés később (mind visszafelé kompatibilis)

| Mit | Hogyan |
|---|---|
| **Nagyobb szerver** | Hetzner Console → Server → **Rescale** (CX32/CX42…) — pár perc állás, Coolify újraindul. A CX22 sok forgalmat elbír, mert a média R2/CDN-en van. |
| **GPS sugaras keresés** | `apt install postgresql-16-postgis-3` a DB-konténerbe (vagy managed PostGIS-es DB), `CREATE EXTENSION postgis;`, a GIST index kézzel (ld. `create_events_table` migráció komment), `/admin/settings/location-search` → BE. Nincs adatmigráció. |
| **Szerver-oldali videókódolás** | `apt install ffmpeg` (vagy a Nixpacks configba `ffmpeg`), `MEDIA_VIDEO_MODE=pipeline`. Az új feltöltések pipeline-t kapnak; a régi `preprocessed` videók változatlanul mennek. |
| **Redis** (gyorsabb cache/queue) | Coolify → + Database → Redis; `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=<coolify_redis>`. |
| **Managed Postgres** | `DB_*` átirányítása (pl. Neon — támogat PostGIS-t is); Coolify csak az appot futtatja. |
| **Külön worker/DB gép** | Coolify multi-server (több szerver egy instance alatt), vagy load balancer + több app-node. |
| **Váltás Forge / Laravel Cloud** | Semmi Coolify-specifikus a kódban — sima Laravel app. `git remote` marad, új platform, env átmásol. |

---

## Tömeges import (5000+ kép egy rendezvényről)

A böngészős feltöltés kötegenként max 200 fájl — nagy rendezvényhez lassú.
Helyette: a fájlokat egy **R2 „drop zone" bucketbe** töltöd, az admin egy
kattintással az eseményhez importálja az egész mappát.

1. **Egyszeri setup**: `R2_IMPORT_BUCKET=kanyarfotozas-import` +
   `MEDIA_IMPORT_DISK=r2_import` az env-ben (fent). (SFTP-szerverrel: `MEDIA_IMPORT_DISK=nas`.)
2. **Feltöltés a gépedről** — [rclone](https://rclone.org)-nal (egyszeri config:
   `rclone config` → új `r2` remote, „Amazon S3" / „Cloudflare R2", az R2 S3
   kulcsokkal):
   ```bash
   rclone copy "D:\fotok\2026-06-hungaroring" r2:kanyarfotozas-import/2026-06-hungaroring \
     --transfers 16 --progress
   ```
   (Preprocessed videó: a `klip.mp4` + `klip_lores.mp4` + `klip.jpg` hármast
   ugyanabba a mappába.)
3. **Import az adminban**: az esemény oldalán → „Beolvasás tárolóból" → megnyitod
   a mappát → bejelölöd a mappa checkboxát (vagy „Az összes fájl ebben a mappában")
   → Importálás. 25 fájl felett a **`imports` queue-n, háttérben** fut, az esemény
   oldalán **folyamatjelzővel** (X / Y). A média fokozatosan `processing` →
   `ready` lesz.
   - **Admin**: a bucket teljes gyökerét látja, bármely fotós nevében importálhat.
   - **Fotós**: csak a saját almappáját (`fotosok/{a-fotós-id}/…`) — ide másol
     (`rclone copy MAPPA r2:kanyarfotozas-import/fotosok/<id>/…`), és mindig a
     saját nevében importál. A fotós-id az admin felületén az import-panelben látszik.
4. **Sebesség**: 1 worker ~feldolgoz pár fájl/mp-et (letöltés R2-ből + thumbnail +
   vízjel + R2-re vissza). 5000 képhez futtass 2–3 párhuzamos workert
   (Coolify → a worker process replikái), vagy indítsd el este.
5. **Takarítás**: sikeres import után az `import` bucket tartalma törölhető
   (`rclone purge r2:kanyarfotozas-import/2026-06-hungaroring`) — az eredetik már
   az archív (`r2_private`) bucketben vannak.

Finomhangolás env-ből: `MEDIA_IMPORT_CHUNK_SIZE` (alap 100),
`MEDIA_IMPORT_INLINE_MAX` (alap 25), `MEDIA_IMPORT_HARD_CAP` (alap 20000).

---

## Tartalom helyben → élesre (indulás előtt, 1:1)

Cél: helyben feltöltöd az igazi tartalmat (események, oldalszövegek, GYIK, árak,
fotósok, hero-képek, minta-galériák), teszteled, majd **ugyanazt** átviszed
élesre — adatostul, képestül.

**Alapelv:** indulás ELŐTT a helyi gép a „mester". Az élesre vitel = a helyi
**adatbázis** + a helyi **média** átmásolása. Ezt akárhányszor megismételheted,
amíg élesbe nem állsz. **Élesítés (első valódi rendelés) UTÁN** ez megfordul: az
éles admin lesz a mester, és egy újabb „helyi → éles" felülírná a valódi
rendeléseket. Onnantól a tartalmat közvetlenül az éles adminban szerkeszted.

### 1. Rögzítsd az `APP_KEY`-t — MOST, mindkét helyre ugyanazt

A `site_settings` titkosított mezői (fizetési kulcsok, SMTP-jelszó, webhook-URL,
2FA-titkok, a superadminod 2FA-ja) az `APP_KEY`-jel vannak titkosítva. Ha helyben
X kulccsal titkosítod és élesen más a kulcs, ezek **olvashatatlanná válnak**
(a rendszer nem omlik össze, csak „nincs beállítva"-ként viselkedik).

```bash
# helyi gépen, a projektben:
php artisan key:generate --show      # base64:....
```

- Írd be a **helyi** `.env`-be `APP_KEY=base64:...`.
- Ugyanezt az értéket add meg élesen a Coolify env-ben (6/b–6/c pont).
- **Soha ne cseréld** egyik helyen sem.

### 2. Tiszta kiindulás helyben (demo-adat nélkül)

Ha korábban `migrate:fresh --seed`-et futtattál, a DB tele van **demo** eseményekkel,
fotósokkal, placeholder-képekkel (`@example.test` e-mailek). Ezek NEM kellenek élesre.
Indíts tisztán, csak a valódi alapadatokkal:

```bash
php artisan migrate:fresh
php artisan db:seed --class=CountrySeeder          # országlista
php artisan db:seed --class=RolePermissionSeeder   # szerepkörök
php artisan db:seed --class=LandingSectionSeeder   # főoldal-blokkok
php artisan db:seed --class=SiteSettingSeeder      # alapbeállítások
php artisan db:seed --class=FaqItemSeeder          # GYIK alaptartalom
```
(Ez a `DatabaseSeeder`, csak a `DemoDataSeeder` nélkül.)

Majd hozz létre egy **valódi** superadmint (nem `password123`):

```bash
php artisan tinker --execute "App\Models\User::create(['name'=>'Zoli','email'=>'bzoli82@gmail.com','password'=>bcrypt('<ERŐS_JELSZÓ>'),'role'=>'superadmin','is_active'=>true]);"
```

### 3. Állítsd a helyi médiát R2-re (hogy oda kerüljön, ahonnan az éles olvas)

Így minden feltöltésed egyből az R2-be megy, és élesen nincs mit másolni —
csak a DB-t.

- Helyi `.env` (a 4. szakasz R2-kulcsaival):
  ```dotenv
  MEDIA_PUBLIC_DISK=r2_public
  MEDIA_ARCHIVE_DISK=r2_private
  MEDIA_DELIVERY_DISK=delivery          # marad lokális — csak gyorsítótár
  R2_ACCESS_KEY_ID=...
  R2_SECRET_ACCESS_KEY=...
  R2_DEFAULT_REGION=auto
  R2_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com
  R2_PUBLIC_BUCKET=kanyarfotozas-public
  R2_PRIVATE_BUCKET=kanyarfotozas-private
  R2_PUBLIC_URL=https://media.kanyarfotozas.hu
  ```
  ```bash
  php artisan config:clear
  ```
- **Ha már van feltöltött médiád lokálisan**, told fel egyszer:
  ```bash
  php artisan kanyarfotozas:sync-media-storage --from-public=public --from-archive=local --dry-run
  php artisan kanyarfotozas:sync-media-storage --from-public=public --from-archive=local
  ```
  Ez a `Media` fájljait viszi. A többi publikus fájlt (hero-képek, fotós
  profilképek, SEO OG-kép) egy sima tükrözéssel:
  ```bash
  # rclone-nal (állítsd be egy `r2` remote-ot az R2 S3 kulcsokkal):
  rclone copy storage/app/public r2:kanyarfotozas-public --exclude "thumbnails/**" --exclude "watermarked/**" --exclude "sprites/**" --exclude "hls/**"
  ```
  > A legegyszerűbb viszont: **a 3. lépést a tartalomfeltöltés ELŐTT** csináld meg —
  > akkor minden egyből R2-re kerül, és ez a felfele-tükrözés kimarad.

### 4. Vidd át az adatbázist élesre

Miután a Coolify Postgres létezik (5. szakasz) és a kód deployolva van (9.):

```bash
# HELYBEN — dump (adat + séma + migrations tábla):
pg_dump --no-owner --no-privileges \
  --exclude-table-data=sessions --exclude-table-data=cache \
  --exclude-table-data=cache_locks --exclude-table-data=jobs \
  -h 127.0.0.1 -U <helyi_user> kanyarfotozas > kf-content.sql
```

Töltsd fel a `kf-content.sql`-t a szerverre (`scp kf-content.sql root@<IP>:/root/`),
majd a Coolify Postgres konténerébe:

```bash
# a szerveren — ürítsd a friss (üres/migrált) éles DB-t, majd töltsd be a dumpot:
docker exec -i <coolify_pg_konténer> psql -U <db_user> -d <db_név> -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker exec -i <coolify_pg_konténer> psql -U <db_user> -d <db_név> < /root/kf-content.sql
```

Majd a Coolify App **Terminal**-jából:

```bash
php artisan migrate:status     # minden „Ran" — a kód és a DB azonos verzión
php artisan config:cache
php artisan queue:restart
```

### 5. Ellenőrzés

- `https://kanyarfotozas.hu` — a főoldal a helyi tartalommal jön (hero, statisztika).
- Belépés a **helyi** superadmin-jelszavaddal (a user átjött a dumpban).
  A 2FA is működik, ha az `APP_KEY` egyezik (1. pont).
- Egy esemény galériája: a képek betöltenek (az R2 `media.` domainről).
- `/admin/settings/critical` — a titkosított mezők (ha adtál meg ilyet helyben)
  olvashatók → az `APP_KEY` egyezik.

### 6. Ismételhető, amíg nem élesedsz

Amíg nincs valódi rendelés, a 4. lépés (dump → drop schema → restore) akárhányszor
megismételhető: dolgozol helyben, újra kiviszed. **Az első éles rendelés után
ne** — onnantól az éles az igazság forrása.

---

## Hibaelhárítás

- **„Vite manifest not found"** a deploy után → az `npm run build` nem futott le a
  buildben; nézd a build logot, a `public/build/manifest.json`-nak létre kell jönnie.
- **500 a kezdőlapon, üres log** → `APP_KEY` hiányzik vagy hibás.
- **Feltöltött kép „processing"-ben ragad** → a **queue worker** nem fut (11. pont).
- **Az ütemező-életjel sárga a `/admin/settings/critical`-on** → a **scheduler**
  (`schedule:run` percenként) nem fut (7. pont).
- **Fizetés után nincs letöltés** → a delivery-cache kötet efemer, vagy nincs
  csatolva (6/a). `php artisan kanyarfotozas:retry-order-fulfillment`.
- **E-mail nem érkezik** → SMTP hibás VAGY SPF/DKIM hiányzik (12. pont);
  `storage/logs/laravel.log`.
