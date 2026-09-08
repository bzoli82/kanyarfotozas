<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * „Webes ütemező" — ha a szerveren nincs mód rendes cront / Scheduled Task-ot
 * beállítani, a superadmin bekapcsolhatja ezt, és a kapott titkos URL-t beilleszti
 * egy ingyenes külső ütemező szolgáltatásba (pl. cron-job.org), ami percenként
 * meghívja. A hívás elindítja a soron következő ütemezett feladatokat (a
 * `routes/console.php` `Schedule::command(...)` sorait).
 *
 * A tényleges munka a HTTP-válasz UTÁN fut (`afterResponse`), hogy a hívó gyors
 * 200-at kapjon; a MIN_INTERVAL-nál sűrűbb hívásokat kihagyja.
 */
class WebScheduler
{
    /** Két tényleges lefutás közti minimum (a félrekonfigolt, túl sűrű hívás ellen). */
    private const MIN_INTERVAL_SECONDS = 50;

    private const LAST_TICK_KEY = 'web_scheduler.last_tick';

    public function enabled(): bool
    {
        return (bool) SiteSetting::get('web_scheduler_enabled', false);
    }

    public function token(): string
    {
        $token = (string) SiteSetting::get('web_scheduler_token', '');

        if ($token === '') {
            SiteSetting::set('web_scheduler_token', $token = Str::lower(Str::random(48)));
        }

        return $token;
    }

    public function url(): string
    {
        return url('/api/ops/scheduler/'.$this->token());
    }

    public function tokenMatches(string $candidate): bool
    {
        return hash_equals($this->token(), $candidate);
    }

    public function setEnabled(bool $enabled): void
    {
        SiteSetting::set('web_scheduler_enabled', $enabled ? '1' : '0');

        if ($enabled) {
            $this->token();
        }
    }

    public function regenerateToken(): string
    {
        SiteSetting::set('web_scheduler_token', $token = Str::lower(Str::random(48)));

        return $token;
    }

    /**
     * A hívó (külső cron) meghívja — a soron következő feladatokat a válasz után indítja.
     *
     * @return 'dispatched'|'throttled'
     */
    public function tick(): string
    {
        $now = now()->timestamp;

        if ($now - (int) Cache::get(self::LAST_TICK_KEY, 0) < self::MIN_INTERVAL_SECONDS) {
            return 'throttled';
        }

        Cache::put(self::LAST_TICK_KEY, $now, now()->addHour());

        ignore_user_abort(true);

        dispatch(function () {
            Artisan::call('schedule:run');
        })->afterResponse();

        return 'dispatched';
    }

    /**
     * Szinkron futtatás az admin „Teszt most" gombjához — visszaadja a kimenetet.
     *
     * @return array{output: string}
     */
    public function runNow(): array
    {
        Artisan::call('schedule:run');

        return ['output' => trim(Artisan::output()) ?: 'Nincs esedékes feladat ebben a percben.'];
    }
}
