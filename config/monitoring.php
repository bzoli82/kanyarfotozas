<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hibakövetés
    |--------------------------------------------------------------------------
    |
    | A kezeletlen kivételeket az App\Services\ErrorReporter rögzíti az
    | `error_events` táblába (ujjlenyomat szerint csoportosítva), és — ha be van
    | állítva — e-mailt küld a superadminoknak / POST-ol egy webhookra.
    | Ugyanarra a hibára `error_cooldown_minutes` percenként megy csak értesítés.
    |
    */

    'error_cooldown_minutes' => (int) env('ERROR_COOLDOWN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Automatikus adatbázis-mentés (roadsidephoto:backup)
    |--------------------------------------------------------------------------
    |
    | Napi `pg_dump` (gzip-elve) a megadott diskre. A `keep` a megtartott
    | legutóbbi mentések száma — a régebbieket a parancs törli.
    |
    */

    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),

    'backup_disk' => env('BACKUP_DISK', 'local'),

    'backup_path' => env('BACKUP_PATH', 'backups'),

    'backup_keep' => (int) env('BACKUP_KEEP', 14),

    'backup_timeout' => (int) env('BACKUP_TIMEOUT', 600),

];
