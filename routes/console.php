<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Utemezo eletjel — a /admin/settings/critical oldal ebbol tudja, hogy fut-e a cron
Schedule::command('kanyarfotozas:heartbeat')->everyFiveMinutes();

// EPIC-12 — automatikus e-mailek (a szerveren `php artisan schedule:work` vagy cron: `* * * * * php artisan schedule:run`)
Schedule::command('kanyarfotozas:send-download-reminders')->hourly();

// EPIC-18 — proaktiv dashboard-figyelmeztetesek ellenorzese + e-mail a superadminoknak
Schedule::command('kanyarfotozas:scan-alerts')->everyFifteenMinutes();

Schedule::command('kanyarfotozas:send-weekly-photographer-reports')->weeklyOn(1, '08:00');
Schedule::command('kanyarfotozas:send-monthly-photographer-reports')->monthlyOn(1, '08:00');

// Napi adatbazis-mentes (pg_dump -> gzip -> a beallitott disk)
Schedule::command('kanyarfotozas:backup')->dailyAt('03:15');

// A kezbesitesi gyorsitotar: elakadt masolasok ujraprobalasa + a lejart/kimerult
// letoltesi tokenu rendelesek gyorsitotarat toroljuk.
Schedule::command('kanyarfotozas:retry-order-fulfillment')->everyFifteenMinutes();
Schedule::command('kanyarfotozas:purge-delivery-cache')->hourly();
