<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Utemezo eletjel — a /admin/settings/critical oldal ebbol tudja, hogy fut-e a cron
Schedule::command('roadsidephoto:heartbeat')->everyFiveMinutes();

// EPIC-12 — automatikus e-mailek (a szerveren `php artisan schedule:work` vagy cron: `* * * * * php artisan schedule:run`)
Schedule::command('roadsidephoto:send-download-reminders')->hourly();

// Elhagyott kosár — egyszeri emlékeztető a 24 órán belül félbehagyott vásárlásokról
Schedule::command('roadsidephoto:send-abandoned-cart-reminders')->hourly();

// EPIC-18 — proaktiv dashboard-figyelmeztetesek ellenorzese + e-mail a superadminoknak
Schedule::command('roadsidephoto:scan-alerts')->everyFifteenMinutes();

Schedule::command('roadsidephoto:send-weekly-photographer-reports')->weeklyOn(1, '08:00');
Schedule::command('roadsidephoto:send-monthly-photographer-reports')->monthlyOn(1, '08:00');

// Napi adatbazis-mentes (pg_dump -> gzip -> a beallitott disk)
Schedule::command('roadsidephoto:backup')->dailyAt('03:15');

// A kezbesitesi gyorsitotar: elakadt masolasok ujraprobalasa + a lejart/kimerult
// letoltesi tokenu rendelesek gyorsitotarat toroljuk.
Schedule::command('roadsidephoto:retry-order-fulfillment')->everyFifteenMinutes();
Schedule::command('roadsidephoto:purge-delivery-cache')->hourly();

// Arva kozvetlen-feltoltes mappak (bongeszo -> R2, importra soha nem kerult).
Schedule::command('roadsidephoto:purge-import-uploads')->dailyAt('04:10');
