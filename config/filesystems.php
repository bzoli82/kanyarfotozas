<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Kézbesítési gyorsítótár (delivery cache) — a megvásárolt, letölthető
         * fájlok másolata egy gyors, mindig elérhető lokális diskre, hogy a
         * vásárló-oldali letöltés független legyen a lassú/törékeny archív
         * rétegtől (NAS SFTP / R2). Efemer: a `roadsidephoto:purge-delivery-cache`
         * takarítja (lejárt/kimerült token után). Lásd App\Services\OrderFulfillment.
         */
        'delivery' => [
            'driver' => 'local',
            'root' => storage_path('app/delivery'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2 (S3-kompatibilis) — élesben ez váltja ki a `public` és a
         * `nas` diskeket (lásd `config/media.php` -> `disks`).
         *   - `r2_public`: kis, publikusan kiszolgált fájlok (thumbnail, vízjelezett
         *     előnézet, HLS szegmensek, hero) — publikus bucket + saját domain / CDN
         *     (`R2_PUBLIC_URL`), így a böngésző közvetlenül a CDN-ről tölti.
         *   - `r2_private`: feltöltött eredeti + megvásárolt letölthető JPEG/WebP/MP4
         *     — privát bucket, csak a `DownloadController` streameli aláírt kérésből.
         * Mindkettő ugyanazt az account-szintű S3 API endpointot használja
         * (`https://<accountid>.r2.cloudflarestorage.com`), csak a bucket tér el.
         */
        'r2_public' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_PUBLIC_BUCKET'),
            'url' => env('R2_PUBLIC_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        'r2_private' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        /*
         * R2 „drop zone" a tömeges importhoz (App\Services\FtpImport, ha
         * MEDIA_IMPORT_DISK=r2_import). Ide rclone-nal / S3-klienssel feltöltöd a
         * teljes méretű eredetiket mappákba, az admin az eseményhez importálja.
         * Külön bucket, hogy ne keveredjen a feldolgozott archívval.
         */
        'r2_import' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_IMPORT_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        /*
         * Tavoli NAS (SFTP): a teljes felbontasu eredeti kepek/videok es a
         * megvasarolt letoltheto JPEG/WebP/MP4 verziok itt taroldnak, hogy a
         * webhosting tarhelyet ne a nagy (5-10+ MB-os) fajlok fogyasszak.
         * A kis meretu thumbnail/vizjelezett elonezet tovabbra is a 'public' diskre kerul.
         */
        'nas' => [
            'driver' => 'sftp',
            'host' => env('NAS_HOST'),
            'port' => (int) env('NAS_PORT', 22),
            'username' => env('NAS_USERNAME'),
            'password' => env('NAS_PASSWORD'),
            'privateKey' => env('NAS_PRIVATE_KEY'),
            'passphrase' => env('NAS_PRIVATE_KEY_PASSPHRASE'),
            'root' => env('NAS_ROOT', '/roadsidephoto'),
            'timeout' => 30,
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
