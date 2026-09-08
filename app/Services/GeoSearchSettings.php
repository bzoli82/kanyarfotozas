<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * GPS sugaras helyszín-keresés kapcsoló.
 *
 * A funkció PostgreSQL + **PostGIS** kiterjesztést igényel: az `EventSearch` a
 * `ST_DWithin` / `ST_Distance` térbeli függvényeket használja a „X km-en belüli
 * események" szűréshez és a távolság szerinti rendezéshez. PostGIS nélküli
 * (vagy PostGIS-t nem támogató managed) adatbázison ezek a lekérdezések hibát
 * dobnának, ezért a funkció alapból **KI** van kapcsolva, és csak akkor
 * kapcsolható be, ha a hosting biztosítja a PostGIS-t.
 *
 * Kikapcsolva: a látogató kereshet helyszínnév / ország / dátum / fotós / típus
 * szerint (ezek sima SQL), és a térkép is működik — csak a „tőlem X km-re" GPS
 * sugaras szűrés + a találatok távolság szerinti listája tűnik el.
 */
class GeoSearchSettings
{
    public function enabled(): bool
    {
        return (bool) SiteSetting::get('geo_search_enabled', false);
    }

    public function update(bool $enabled): void
    {
        SiteSetting::set('geo_search_enabled', $enabled ? '1' : '0');
    }
}
