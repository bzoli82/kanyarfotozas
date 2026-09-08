<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FFmpeg / FFprobe binary paths (EPIC-05 videofeldolgozo pipeline)
    |--------------------------------------------------------------------------
    |
    | Alapertelmezetten csak a parancs nevet hasznaljuk (feltetelezve, hogy a
    | PATH-on van) — de production kornyezetben, vagy ha a PATH nem
    | frissult meg egy uj binaris telepitese utan, explicit utvonal adhato meg.
    |
    */

    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),

    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),

    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Videó-mód: szerver-oldali kódolás vagy elő-feldolgozott feltöltés
    |--------------------------------------------------------------------------
    |
    |   pipeline     : (alap) a szerver FFmpeg-gel legyártja a thumbnailt, a
    |                  vízjelezett 720p előnézetet, a scrub sprite-ot és a HLS
    |                  streamet — App\Jobs\ProcessVideoMedia.
    |   preprocessed : NINCS szerver-oldali FFmpeg. A fotós a helyi gépén
    |                  előre elkészíti a kis felbontású előnézetet, és a
    |                  következő fájl-hármast tölti fel (közös alapnév):
    |                    foo.mp4        – teljes felbontású eredeti (a termék)
    |                    foo_lores.mp4  – kis felbontású, vízjelezett előnézet
    |                    foo.jpg        – állókép poszter (opcionális)
    |                  A galéria-kártyán statikus poszter + lejátszás-ikon,
    |                  kattintásra a lores előnézet játszódik le. Nincs
    |                  scrub sáv és nincs HLS.
    |
    */

    'video_mode' => env('MEDIA_VIDEO_MODE', 'pipeline'),

    // A kis felbontású előnézet-fájl kötelező utótagja preprocessed módban.
    'preprocessed_lores_suffix' => '_lores',

    /*
    |--------------------------------------------------------------------------
    | HLS streaming (EPIC-16)
    |--------------------------------------------------------------------------
    |
    | A vizjelezett elonezet-video HLS valtozata: 10 mp-es .ts szegmensek + egy
    | master.m3u8, ket adaptiv minoseggel. Elesben a `public` disk URL-jet
    | erdemes Cloudflare CDN moge tenni (a .ts szegmensek jol cache-elhetok).
    |
    */

    'hls_segment_seconds' => env('HLS_SEGMENT_SECONDS', 10),

    'hls_variants' => [
        // magassag => video bitrate
        720 => '2M',
        480 => '1M',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendszámfelismerés (EPIC-13)
    |--------------------------------------------------------------------------
    |
    | A tényleges szolgáltató (Plate Recognizer) kulcsa NEM itt van, hanem
    | titkosítva a `site_settings`-ben (App\Services\PlateRecognitionSettings),
    | és az egész funkció adminból teljesen kikapcsolható.
    |
    */

    // A homályosítandó régió Intervention blur-erőssége (0-100, ~sigma 15 megfelelője).
    'plate_blur_strength' => env('PLATE_BLUR_STRENGTH', 45),

    // Videónál mely másodpercek kockáin fusson a felismerés (a legjobb találat számít).
    'plate_video_sample_seconds' => [1, 5, 10],

    /*
    |--------------------------------------------------------------------------
    | Média tároló diskek (Cloudflare R2 vagy lokális)
    |--------------------------------------------------------------------------
    |
    | A pipeline mindig a LOKÁLIS `local` diskre tölt fel és ott dolgozza fel az
    | eredetit (az Intervention / FFmpeg valós fájl-útvonalat igényel). Utána:
    |
    |   - `public`  : a kis, publikusan kiszolgált fájlok (thumbnail, vízjelezett
    |                 előnézet, HLS szegmensek, hero) — dev: `public` disk,
    |                 éles: `r2_public` (saját domain / CDN az `R2_PUBLIC_URL`-ből).
    |   - `archive` : a nagy fájlok (feltöltött eredeti + letölthető JPEG/WebP/MP4)
    |                 hosszú távú tárolója — dev: `nas` (SFTP), éles: `r2_private`.
    |                 Ha az értéke `local`, nincs külön archív réteg: az eredeti a
    |                 `local` diskon marad és az archiváló job kihagyja a mozgatást.
    |
    | Így az R2-re váltás pusztán env-kérdés, a kód nem változik.
    |
    */

    'disks' => [
        'public' => env('MEDIA_PUBLIC_DISK', 'public'),
        'archive' => env('MEDIA_ARCHIVE_DISK', 'nas'),
        // Kézbesítési gyorsítótár — a megvásárolt fájlok gyors, mindig elérhető
        // másolata (a vásárló-oldali letöltés így nem függ az archív rétegtől).
        'delivery' => env('MEDIA_DELIVERY_DISK', 'delivery'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kézbesítési gyorsítótár élettartama
    |--------------------------------------------------------------------------
    |
    | A megvásárolt fájlok másolata addig marad a `delivery` diskon, amíg a
    | letöltési token él (72 óra / 5 megnyitás), plusz egy biztonsági felső
    | korlát az árván maradt bejegyzésekre.
    |
    */

    'delivery_max_age_hours' => env('MEDIA_DELIVERY_MAX_AGE_HOURS', 24 * 7),

];
