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
    | Tömeges import forrás-disk (App\Services\FtpImport)
    |--------------------------------------------------------------------------
    |
    | Melyik diskről olvas az esemény-oldali „Beolvasás tárolóból" böngésző:
    |   nas       : SFTP fájlszerver (a `NasConnection` kulcsaival)
    |   r2_import : dedikált Cloudflare R2 „drop zone" bucket (S3) — ide rclone-nal
    |               vagy bármely S3-klienssel feltöltöd a mappákat, majd az admin
    |               egy kattintással az eseményhez importálja az egészet
    |   local     : a szerver egy helyi mappája (`storage/app/private` alatt)
    |
    | 100+ fájlnál az import a `imports` queue-n, batch job-ban fut, folyamatjelzővel.
    |
    */

    'import_disk' => env('MEDIA_IMPORT_DISK', 'nas'),

    // A fotósok saját, elkülönített almappája az import-tárolóban: a böngészőjük
    // ide van „gyökerezve" ({folder}/{photographer_id}), így nem látják egymás /
    // más események anyagát. Az adminok a tároló teljes gyökerét látják.
    'import_photographer_folder' => env('MEDIA_IMPORT_PHOTOGRAPHER_FOLDER', 'fotosok'),

    // Egy import-batch egy chunk-jában feldolgozott „egység" (kép vagy videó-hármas).
    'import_chunk_size' => (int) env('MEDIA_IMPORT_CHUNK_SIZE', 100),

    // E fölött a fájlszám fölött az import a háttérben (queue batch) fut, alatta azonnal.
    'import_inline_max' => (int) env('MEDIA_IMPORT_INLINE_MAX', 25),

    // Egy import-hívásban feldolgozott fájlok abszolút felső korlátja.
    'import_hard_cap' => (int) env('MEDIA_IMPORT_HARD_CAP', 20000),

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
