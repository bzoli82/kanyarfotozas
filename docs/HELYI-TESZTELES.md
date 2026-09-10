# Helyi tesztelés — működés, fizetés, tároló-váltás

Ez a fájl azt írja le, hogyan lehet a RoadsidePhoto platformot a **saját gépen**
(dev) végigtesztelni: a normál működést, a **fizetést**, és a **megvásárolt
fájlok tároló-váltását** (saját NAS/SFTP ↔ Cloudflare R2) + a **NAS ↔ R2
szinkront**.

> A projekt Windows dev gépen fut, `php artisan serve` (port 8000), PostgreSQL,
> `QUEUE_CONNECTION=database`, `MAIL_MAILER=log` (a levelek a
> `storage/logs/laravel.log`-ba mennek, nem küldődnek ki), videó-mód `pipeline`
> (helyi FFmpeg). Dev belépés: `superadmin@roadsidephoto.eu` / `password`.

---

## 0. Alap — mi fusson

Két folyamat kell egyszerre:

```bash
php artisan serve --port=8000
php artisan queue:work --queue=videos,imports,default
```

A **queue worker nélkül** nem fut le: a kép/videó-feldolgozás, az archiválás
(`ArchiveMediaOriginalToNas`), a NAS↔R2 szinkron, a fizetés utáni lépések
(számla, kézbesítési gyorsítótár, e-mail).

> A `serve` beépített szervere alatt a `pg_dump`-os funkciók (adat-szinkron
> „Mentés most", backup) elhasalnak egy Windows temp-fájl hiba miatt — azok CLI-ből
> (`php artisan roadsidephoto:backup`) és éles Linuxon mennek. A fizetést és a
> tároló-váltást ez **nem** érinti.

Beállítás-változás után futó workernél: a `SiteSetting` 300 mp-ig cache-elt, és a
worker boot-kor olvassa a configot — állítsd le / indítsd újra a workert, hogy
azonnal érvényes legyen.

---

## 1. Megvásárolt fájlok — tároló-váltás + NAS ↔ R2 szinkron

**Felület:** `/admin/settings/storage` → „Megvásárolt fájlok — honnan töltsük le".

A megvásárolható nagy fájlok (feltöltött eredeti + letölthető JPEG/WebP/MP4)
tárolója futásidőben átbillenthető **saját SFTP/NAS** és **Cloudflare R2 privát
bucket** között — nem kell `.env`-et szerkeszteni. A választás a
`site_settings.media_archive_disk`-be kerül, és boot-kor felülírja a
`MEDIA_ARCHIVE_DISK` env-et (`App\Services\ArchiveStorage::applyRuntimeConfig`).

### 1.a Csak a logikát tesztelni (külső szolgáltatás nélkül)

A 7 automata teszt lefedi a disk-váltást + a fájl-másolást + a batch indítást:

```bash
php artisan test --filter=ArchiveStorageTest
```

### 1.b Élethű helyi teszt (két valódi tároló)

A felületi kapcsolóhoz + a szinkron-gombokhoz **két beállított tároló kell**.
Helyben ez a szűk keresztmetszet — az egyik legyen R2, a másik egy SFTP végpont.

**Cloudflare R2 (ingyenes, ~10 perc):**

1. Cloudflare → R2 → hozz létre 3 bucketet: `rp-public`, `rp-private`, `rp-import`.
2. „Manage R2 API Tokens" → „Create API Token" → **Object Read & Write** → kapsz
   egy Access Key ID + Secret Access Key párt + az account S3 endpointot
   (`https://<accountid>.r2.cloudflarestorage.com`).
3. `/admin/settings/storage` → „Cloudflare R2" szekció → töltsd ki, **Mentés**,
   majd „Kapcsolat tesztelése" (zöld visszajelzés).

**SFTP „NAS" (ha nincs igazi NAS — eldobható Docker konténer):**

```bash
docker run -d -p 2222:22 --name sftp atmoz/sftp foo:pass:::upload
```

`/admin/settings/storage` → „NAS kapcsolati adatok": host `localhost`, port
`2222`, felhasználó `foo`, jelszó `pass`, gyökér `/upload`. **Mentés** →
„Kapcsolat tesztelése".

**Végigjátszás:**

1. Fut a `queue:work`.
2. Hozz létre egy eseményt, tölts fel 2–3 fotót → feldolgozódnak (`ready`).
   Az eredetik az aktuális archív diskre kerülnek (a
   `ArchiveMediaOriginalToNas` job a `queue:work`-ön fut). A `/admin/settings/storage`
   KPI-jában megjelenik a „NAS-ra archiválva" darabszám.
3. Vásárolj meg egy fotót (lásd 2. szakasz), töltsd le — ekkor a fájl az aktuális
   archív diskről jön (a kézbesítési gyorsítótáron át).
4. **Szinkron:** „NAS → R2 másolás" gomb → a folyamatjelző 3 mp-enként frissül
   (másolva / kihagyva / hiba). Idempotens — újra megnyomva a meglévőt kihagyja.
5. **Váltás:** a „Kiszolgálás innen" legördülőt állítsd a másik tárolóra,
   **Mentés**. (⚠️ Csak azután, hogy a szinkron lefutott — a meglévő archivált
   médiát a rendszer innentől az új diskon keresi.)
6. Indítsd újra a `queue:work`-öt, és tölts le újra egy megvásárolt fájlt →
   most a másik tárolóból jön. Ellenőrizd a bucket / SFTP tartalmát is.

**CLI (nagy adatmennyiséghez, worker nélkül):**

```bash
php artisan roadsidephoto:sync-archive-storage --from=nas --to=r2_private
php artisan roadsidephoto:sync-archive-storage --from=r2_private --to=nas --force
```

---

## 2. Fizetés helyi tesztelése

**Kulcsok helye:** `/admin/settings/critical` (nem `.env`) — a `.env`-ben
szándékosan nincsenek. A `payment.settings` middleware a checkout route-okon a
`site_settings`-ből a configba tölti.

Dev-ben a levelek `log` driverre mennek → a visszaigazoló a
`storage/logs/laravel.log`-ban látszik, nem küldődik ki ténylegesen. A flow így is
végigjátszható.

### 2.a Stripe (a legegyszerűbb helyben)

1. Stripe Dashboard → **Test mode** → Developers → API keys → másold ki:
   `pk_test_…` (publishable), `sk_test_…` (secret).
2. `/admin/settings/critical` → „Fizetés" → Stripe secret + publishable → Mentés.
3. Webhook secret a Stripe CLI-ből:
   ```bash
   stripe login
   stripe listen --forward-to localhost:8000/api/stripe/webhook
   ```
   A parancs kiír egy `whsec_…` értéket — ezt írd a „Stripe webhook secret"
   mezőbe.
4. A pénztárban válaszd a Stripe-ot, teszt-kártya:
   `4242 4242 4242 4242`, bármilyen **jövőbeli** lejárat, tetszőleges CVC + irsz.
5. Sikeres fizetés → visszairányítás a `/checkout/success`-re. Ez **szinkron is**
   lekérdezi a Stripe session állapotát, tehát a rendelés akkor is lezárul, ha a
   webhook épp nem fut — de a Stripe CLI-vel a webhook-ág is tesztelhető.
6. A `queue:work` lefuttatja: számla (ha be van kapcsolva), kézbesítési
   gyorsítótár (`PrepareOrderDownloads`), visszaigazoló e-mail (a logba).
7. A `Download/Show` oldalon töltsd le a fájlokat.

Hibás fizetés tesztje: `4000 0000 0000 0002` (elutasított kártya).

### 2.b SimplePay / Barion (sandbox — publikus URL kell)

Ezek visszahívása (IPN / callback) publikusan elérhető URL-t igényel, ami
`localhost`-on nincs. Két lehetőség:

- **Tunnel** (teljes teszt): `cloudflared tunnel --url http://localhost:8000`
  vagy `ngrok http 8000` → az így kapott `https://…` címet állítsd be
  `APP_URL`-nek (`.env` + `php artisan config:clear`), és azt add meg a
  szolgáltató sandbox-fiókjában visszahívási URL-nek:
  - SimplePay IPN: `POST /api/simplepay/ipn`
  - Barion callback: `POST|GET /api/barion/callback`
- **Csak a visszatérési URL-lel** (részleges): a `GET /checkout/simplepay/return`
  ill. `/checkout/barion/return` böngészőből visszatér — ez „élmény, nem
  megbízható", de a rendelés lezárásához dev-ben elég, ha a szolgáltató
  visszaküldi a felhasználót.

**SimplePay sandbox:** OTP SimplePay sandbox merchant + secret key →
`/admin/settings/critical`, „SimplePay sandbox" checkbox BE.
**Barion sandbox:** Barion sandbox fiók POSKey + a fiók e-mailje (`barion_payee`)
→ `/admin/settings/critical`, „Barion sandbox" BE.

### 2.c Visszatérítés

Az admin rendelés-részletnézetben (`/admin/orders/{order}`) teljes/részleges
visszatérítés — a `queue:work` intézi a sztornó számlát + a
`OrderRefundedMail`-t (logba). Stripe/SimplePay/Barion mind a saját API-ján
hívja a refundot (teszt/sandbox módban díjmentes).

---

## 3. Gyors ellenőrző lista

| Teszt | Mi kell hozzá | Hol nézd az eredményt |
|---|---|---|
| Kép/videó feltöltés + feldolgozás | `queue:work` | esemény média-rács, `status=ready` |
| Archiválás NAS/R2-re | `queue:work` + beállított archív disk | `/admin/settings/storage` KPI |
| Tároló-váltás | 2 beállított tároló | „Kiszolgálás innen" legördülő + letöltés |
| NAS ↔ R2 szinkron | 2 beállított tároló + `queue:work` | folyamatjelző + bucket/SFTP tartalom |
| Stripe fizetés | teszt-kulcsok + `stripe listen` | `/checkout/success`, `/admin/orders` |
| Visszaigazoló e-mail | `queue:work` | `storage/logs/laravel.log` |
| Számla | `queue:work` + Billingo kulcs (Kritikus beáll.) | rendelés-részlet „Számla" panel |
| Letöltés | fizetett rendelés | `/download/{token}` |
