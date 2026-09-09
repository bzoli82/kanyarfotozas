<?php

namespace App\Console\Commands;

use App\Services\SiteBranding;
use App\Services\SiteIdentity;
use Illuminate\Console\Command;

/**
 * A régi (ideiglenes) márkanévre utaló nyomok keresése a kódban és a DB-ben —
 * hogy az átnevezés után egyetlen utalás se maradjon (jogi ok: létező, más
 * vállalkozás neve). Hibakóddal tér vissza, ha talál — CI-ben is használható.
 */
class AuditSiteIdentity extends Command
{
    protected $signature = 'roadsidephoto:audit-identity
        {--token=kanyarfotozas : A keresett (régi) szórészlet}';

    protected $description = 'A régi márkanévre utaló nyomok keresése a kódban és a DB-ben';

    public function handle(SiteIdentity $identity, SiteBranding $branding): int
    {
        $token = mb_strtolower((string) $this->option('token'));
        $exclude = $branding->slug(); // az aktuális (új) azonosító — nem fals találat

        $codeHits = $identity->scanCode($token, $exclude);
        $dbHits = $identity->scanDatabase($token, $exclude);

        if ($codeHits === [] && $dbHits === []) {
            $this->info("Nincs '{$token}' nyom a kódban és a DB-ben.");

            return self::SUCCESS;
        }

        if ($codeHits !== []) {
            $this->warn(count($codeHits).' kód-találat:');
            foreach ($codeHits as $hit) {
                $this->line("  {$hit}");
            }
        }

        if ($dbHits !== []) {
            $this->newLine();
            $this->warn(count($dbHits).' adatbázis-találat:');
            foreach ($dbHits as $hit) {
                $this->line("  {$hit}");
            }
        }

        $this->newLine();
        $this->error('Maradtak nyomok — a DB-t a `roadsidephoto:apply-identity` írja át, a kód-találatokat kézzel.');

        return self::FAILURE;
    }
}
