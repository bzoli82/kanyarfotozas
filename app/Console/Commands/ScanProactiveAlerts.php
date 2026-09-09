<?php

namespace App\Console\Commands;

use App\Mail\ProactiveAlertDigestMail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ProactiveAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * EPIC-18: 15 percenkent ellenorzi a kritikus, el nem rejtett proaktiv
 * figyelmeztetéseket, es ha az aktualis keszlet elter a legutobb kikuldottol,
 * e-mail-osszefoglalot kuld minden superadminnak (max 6 orankent ismetli).
 */
class ScanProactiveAlerts extends Command
{
    protected $signature = 'roadsidephoto:scan-alerts';

    protected $description = 'Kritikus dashboard-figyelmeztetések ellenőrzése és e-mail értesítés a superadminoknak';

    private const STATE_KEY = 'proactive_alerts_notified';

    private const REPEAT_HOURS = 6;

    public function handle(ProactiveAlerts $alerts): int
    {
        $critical = $alerts->criticalVisible();

        if ($critical === []) {
            SiteSetting::set(self::STATE_KEY, null);
            $this->info('Nincs kritikus figyelmeztetés.');

            return self::SUCCESS;
        }

        $signature = md5(implode('|', array_column($critical, 'key')));
        $stored = json_decode((string) SiteSetting::get(self::STATE_KEY), true);

        $sameSet = is_array($stored) && ($stored['signature'] ?? null) === $signature;
        $recent = is_array($stored)
            && isset($stored['at'])
            && now()->parse($stored['at'])->gt(now()->subHours(self::REPEAT_HOURS));

        if ($sameSet && $recent) {
            $this->info('Ugyanaz a figyelmeztetés-készlet, nemrég kiment — kihagyva.');

            return self::SUCCESS;
        }

        $recipients = User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email');

        foreach ($recipients as $email) {
            Mail::to($email)->send(new ProactiveAlertDigestMail($critical));
        }

        SiteSetting::set(self::STATE_KEY, json_encode(['signature' => $signature, 'at' => now()->toIso8601String()]));

        $this->info(count($critical).' kritikus figyelmeztetés kiküldve '.$recipients->count().' superadminnak.');

        return self::SUCCESS;
    }
}
