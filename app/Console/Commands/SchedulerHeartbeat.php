<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * A `site_settings.scheduler_last_run` időbélyeget frissíti — ebből tudja a
 * „Kritikus beállítások" oldal, hogy fut-e egyáltalán a `schedule:run` cron a
 * szerveren (enélkül az emlékeztetők / riportok / riasztások sosem indulnának).
 */
class SchedulerHeartbeat extends Command
{
    protected $signature = 'roadsidephoto:heartbeat';

    protected $description = 'Ütemező életjel — a Kritikus beállítások oldal ellenőrzi';

    public const KEY = 'scheduler_last_run';

    public function handle(): int
    {
        SiteSetting::set(self::KEY, now()->toIso8601String());

        return self::SUCCESS;
    }
}
