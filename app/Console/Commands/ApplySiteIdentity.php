<?php

namespace App\Console\Commands;

use App\Services\SiteIdentity;
use Illuminate\Console\Command;

/**
 * Az oldal VÉGLEGES domainjének alkalmazása mindenütt, ahol a platform a saját
 * (ideiglenes) nevét használja — a nem vizuális objektumokban: felhasználói (és
 * rendelési / feliratkozási) e-mailek + `site_settings` identitás-kulcsok.
 *
 * Ugyanezt az admin felület is elvégzi: /admin/settings/critical → „Az oldal
 * végleges domainje" → Előnézet / Átnevezés. A motor: App\Services\SiteIdentity.
 *
 * A DB ÁTNEVEZÉSE + az `.env` módosítása KÉZI — a parancs a végén kiírja.
 * Ellenőrzés: `kanyarfotozas:audit-identity`.
 */
class ApplySiteIdentity extends Command
{
    protected $signature = 'kanyarfotozas:apply-identity
        {domain : Az új végleges domain, pl. kanyarfotozas.hu}
        {--from= : A régi platform-domain (alapból a superadmin e-mail domainje)}
        {--dry-run : Csak kiírja, mit tenne}';

    protected $description = 'Az oldal végleges domainjének alkalmazása (e-mailek, beállítások) + kézi teendők';

    public function handle(SiteIdentity $identity): int
    {
        $domain = mb_strtolower(trim($this->argument('domain')));

        if (! $identity->looksLikeDomain($domain)) {
            $this->error("Érvénytelen domain: {$domain}");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $plan = $identity->plan($domain, $this->option('from') ?: null);

        $this->info("Régi domain: {$plan['from']}  →  új: {$plan['to']}");
        $this->info("Régi azonosító: {$plan['from_slug']}  →  új: {$plan['to_slug']}");
        $this->newLine();

        $this->line('Felhasználói / rendelési e-mailek ('.count($plan['emails']).' db):');
        foreach ($plan['emails'] as $c) {
            $this->line("  {$c['table']}.{$c['column']}#{$c['id']}:  {$c['old']}  →  {$c['new']}");
        }

        $this->newLine();
        $this->line('site_settings ('.count($plan['settings']).' db):');
        foreach ($plan['settings'] as $c) {
            $this->line("  {$c['key']}:  {$c['old']}  →  {$c['new']}");
        }

        if ($dry) {
            $this->newLine();
            $this->warn('DRY RUN — semmi nem változott.');
        } else {
            $result = $identity->apply($domain, $this->option('from') ?: null);
            $this->newLine();
            $this->info("Kész: {$result['emails_updated']} e-mail + {$result['settings_updated']} beállítás átírva.");
        }

        $this->newLine();
        $this->warn('KÉZI teendők (a futó alkalmazás ezeket nem végezheti el):');
        if ($plan['manual']['needs_db_rename']) {
            $this->line("  1. Adatbázis átnevezése:  {$plan['manual']['db_from']}  →  {$plan['manual']['db_to']}");
            $this->line("       ALTER DATABASE {$plan['manual']['db_from']} RENAME TO {$plan['manual']['db_to']};  (nem a saját kapcsolatból)");
        }
        foreach ($plan['manual']['env'] as $key => $value) {
            $this->line("  .env:  {$key}=\"{$value}\"");
        }
        $this->line('  php artisan config:clear  &&  a queue/scheduler workerek újraindítása');
        $this->line('  Ellenőrzés:  php artisan kanyarfotozas:audit-identity');

        if ($plan['code_hits'] !== []) {
            $this->newLine();
            $this->warn(count($plan['code_hits']).' kód-találat a régi névre (kézzel javítandó):');
            foreach (array_slice($plan['code_hits'], 0, 30) as $hit) {
                $this->line("  {$hit}");
            }
        }

        return self::SUCCESS;
    }
}
