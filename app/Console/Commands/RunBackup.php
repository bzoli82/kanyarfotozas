<?php

namespace App\Console\Commands;

use App\Mail\ProactiveAlertDigestMail;
use App\Models\User;
use App\Services\BackupService;
use App\Services\MonitoringSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Napi adatbázis-mentés (`pg_dump` → gzip → a beállított disk). Ütemezve a
 * routes/console.php-ban. Hiba esetén rögzíti az állapotot és e-mailt küld a
 * superadminoknak.
 */
class RunBackup extends Command
{
    protected $signature = 'kanyarfotozas:backup';

    protected $description = 'Adatbázis-mentés készítése (pg_dump, gzip) a beállított diskre';

    public function handle(BackupService $backup, MonitoringSettings $settings): int
    {
        try {
            $result = $backup->run();
            $this->info("Mentés kész: {$result['name']} (".number_format($result['size'] / 1024, 0, ',', ' ').' KB)');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $settings->recordBackup(false, $e->getMessage());
            $this->error('A mentés nem sikerült: '.$e->getMessage());

            $alert = [[
                'key' => 'backup_failed',
                'severity' => 'critical',
                'title' => 'Az adatbázis-mentés nem sikerült',
                'description' => $e->getMessage(),
                'action_url' => rescue(fn () => route('admin.settings.critical'), null, false),
                'action_label' => 'Kritikus beállítások',
            ]];

            User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email')
                ->each(fn ($email) => rescue(fn () => Mail::to($email)->send(new ProactiveAlertDigestMail($alert)), null, false));

            return self::FAILURE;
        }
    }
}
