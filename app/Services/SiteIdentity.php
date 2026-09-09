<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Az oldal átnevezésének (rebrand) motorja — MINDEN nem vizuális objektumban:
 * a platform saját domainjén levő felhasználói (és rendelési / feliratkozási)
 * e-mailek, a `site_settings` identitás-kulcsai. A vizuális szövegeket (oldal-copy,
 * FAQ) NEM bántja — azokat az admin a saját oldalaikon szerkeszti.
 *
 * A `plan()` előnézetet ad (mit tenne), az `apply()` végrehajtja tranzakcióban.
 * Az adatbázis ÁTNEVEZÉSE + a `.env` módosítása nem történhet futó kérésből —
 * a `plan()['manual']` a pontos lépéseket adja.
 *
 * Jogi ok a teljességre: létező, más vállalkozás neve a régi domain — egyetlen
 * automatikusan javítható nyom se maradhat.
 */
class SiteIdentity
{
    /**
     * Táblák + oszlopok, ahol a platform saját domainjén levő e-mail előfordulhat.
     *
     * @var array<string, string>
     */
    private const EMAIL_COLUMNS = [
        'users' => 'email',
        'orders' => 'buyer_email',
        'contact_messages' => 'email',
        'event_subscriptions' => 'email',
        'data_requests' => 'email',
    ];

    /** Kódszkennelés alapértelmezett mappái. */
    private const CODE_PATHS = ['app', 'config', 'database', 'routes', 'resources', 'lang', 'bootstrap'];

    /** @var list<string> */
    private const CODE_EXTENSIONS = ['php', 'vue', 'js', 'json', 'css', 'yml', 'blade.php'];

    public function __construct(private SiteBranding $branding) {}

    /** A jelenlegi (lecserélendő) domain — a superadmin e-mailjéből, ha van. */
    public function currentDomain(): string
    {
        $superadmin = User::query()->where('role', User::ROLE_SUPERADMIN)->orderBy('id')->value('email');

        if ($superadmin && str_contains($superadmin, '@')) {
            $host = mb_strtolower(explode('@', $superadmin)[1]);
            if ($this->looksLikeDomain($host)) {
                return $host;
            }
        }

        $stored = $this->branding->domain();

        return $this->looksLikeDomain($stored) ? $stored : 'localhost';
    }

    public function currentDbName(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    public function looksLikeDomain(string $value): bool
    {
        return (bool) preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value);
    }

    public function slugFor(string $domain): string
    {
        $label = explode('.', preg_replace('/^www\./', '', mb_strtolower($domain)))[0] ?? $domain;

        return preg_replace('/[^a-z0-9]/', '', $label);
    }

    /**
     * Előnézet: pontosan mi változna. Semmit nem ír.
     *
     * @return array{
     *   from: string, to: string, from_slug: string, to_slug: string, noop: bool,
     *   emails: list<array{table: string, column: string, id: int|string, old: string, new: string}>,
     *   settings: list<array{key: string, old: string, new: string}>,
     *   manual: array{db_from: string, db_to: string, needs_db_rename: bool, env: array<string, string>},
     *   code_hits: list<string>
     * }
     */
    public function plan(string $newDomain, ?string $fromDomain = null): array
    {
        $to = mb_strtolower(trim($newDomain));
        $from = mb_strtolower(trim($fromDomain ?: $this->currentDomain()));
        $toSlug = $this->slugFor($to);
        $fromSlug = $this->slugFor($from);

        // Nincs tényleges átnevezés (a régi és az új név megegyezik) → nincs teendő.
        $noop = $from === $to;
        $needsDbRename = ! $noop && $fromSlug !== '' && $this->currentDbName() !== $toSlug;

        return [
            'from' => $from,
            'to' => $to,
            'from_slug' => $fromSlug,
            'to_slug' => $toSlug,
            'noop' => $noop,
            'emails' => $this->emailChanges($from, $to),
            'settings' => $this->settingChanges($from, $to, $fromSlug, $toSlug),
            'manual' => [
                'db_from' => $this->currentDbName(),
                'db_to' => $toSlug,
                'needs_db_rename' => $needsDbRename,
                'env' => [
                    'DB_DATABASE' => $toSlug,
                    'APP_NAME' => $this->branding->name(),
                    'APP_URL' => 'https://'.$to,
                    'MAIL_FROM_ADDRESS' => 'noreply@'.$to,
                ],
            ],
            'code_hits' => $noop ? [] : $this->scanCode($fromSlug ?: 'kanyarfotozas', $toSlug),
        ];
    }

    /**
     * Végrehajtás — tranzakcióban. Csak a nem vizuális adatot írja át.
     *
     * @return array{from: string, to: string, emails_updated: int, settings_updated: int}
     *
     * @throws \InvalidArgumentException érvénytelen domain esetén
     */
    public function apply(string $newDomain, ?string $fromDomain = null): array
    {
        $to = mb_strtolower(trim($newDomain));

        if (! $this->looksLikeDomain($to)) {
            throw new \InvalidArgumentException("Érvénytelen domain: {$to}");
        }

        $from = mb_strtolower(trim($fromDomain ?: $this->currentDomain()));
        $toSlug = $this->slugFor($to);
        $fromSlug = $this->slugFor($from);

        $emailChanges = $this->emailChanges($from, $to);
        $settingChanges = $this->settingChanges($from, $to, $fromSlug, $toSlug);

        DB::transaction(function () use ($emailChanges, $settingChanges, $to) {
            foreach ($emailChanges as $c) {
                DB::table($c['table'])->where('id', $c['id'])->update([$c['column'] => $c['new']]);
            }

            foreach ($settingChanges as $c) {
                SiteSetting::set($c['key'], $c['new']);
            }

            // A domaint mindenképp rögzítjük (akkor is, ha épp nem szerepelt beállításban).
            $this->branding->setDomain($to);
        });

        return [
            'from' => $from,
            'to' => $to,
            'emails_updated' => count($emailChanges),
            'settings_updated' => count($settingChanges),
        ];
    }

    /**
     * @return list<array{table: string, column: string, id: int|string, old: string, new: string}>
     */
    private function emailChanges(string $from, string $to): array
    {
        if ($from === '' || $from === $to) {
            return [];
        }

        $changes = [];

        foreach (self::EMAIL_COLUMNS as $table => $column) {
            try {
                $rows = DB::table($table)
                    ->whereRaw("LOWER({$column}) LIKE ?", ['%@'.$from])
                    ->get(['id', $column]);
            } catch (\Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $old = (string) $row->{$column};
                $new = preg_replace('/@'.preg_quote($from, '/').'$/i', '@'.$to, $old);

                if ($new !== null && $new !== $old) {
                    $changes[] = ['table' => $table, 'column' => $column, 'id' => $row->id, 'old' => $old, 'new' => $new];
                }
            }
        }

        return $changes;
    }

    /**
     * @return list<array{key: string, old: string, new: string}>
     */
    private function settingChanges(string $from, string $to, string $fromSlug, string $toSlug): array
    {
        $changes = [];
        $seen = [];

        // 1) A domaint tartalmazó összes site_settings érték.
        foreach (SiteSetting::query()->get(['key', 'value']) as $row) {
            $value = (string) $row->value;
            $new = $value;

            if ($from !== '' && stripos($value, $from) !== false) {
                $new = str_ireplace($from, $to, $new);
            }
            // A slug-cserét csak akkor, ha a régi slug önmagában szerepel ÉS az új
            // slug még nem — így a `roadsidephoto.eu` (= már az új azonosító) nem sérül.
            if (
                $fromSlug !== '' && $fromSlug !== $toSlug
                && stripos($new, $fromSlug) !== false
                && stripos($new, $toSlug) === false
            ) {
                $new = str_ireplace($fromSlug, $toSlug, $new);
            }

            if ($new !== $value) {
                $changes[] = ['key' => $row->key, 'old' => $value, 'new' => $new];
                $seen[$row->key] = true;
            }
        }

        // 2) A site_domain-t mindig a célértékre állítjuk. (Nincs default a get()-nél,
        // különben az üres string bekerülne a SiteSetting cache-be.)
        if (! isset($seen['site_domain'])) {
            $current = (string) (SiteSetting::get('site_domain') ?? '');
            if ($current !== $to) {
                $changes[] = ['key' => 'site_domain', 'old' => $current, 'new' => $to];
            }
        }

        return $changes;
    }

    /**
     * A régi név nyomai a forráskódban (csak olvasás). A `$exclude` (az ÚJ slug)
     * előfordulásait előbb kivágja, hogy az új `roadsidephoto:*` hivatkozások ne
     * legyenek fals találatok. A `SiteIdentity` + a két identitás-parancs saját
     * fájljait kihagyja.
     *
     * @return list<string>
     */
    public function scanCode(string $token, string $exclude = ''): array
    {
        $token = mb_strtolower($token);
        $exclude = mb_strtolower($exclude);
        if ($token === '') {
            return [];
        }

        $base = base_path();
        $skip = ['SiteIdentity.php', 'ApplySiteIdentity.php', 'AuditSiteIdentity.php'];
        $hits = [];

        foreach (self::CODE_PATHS as $path) {
            $dir = $base.DIRECTORY_SEPARATOR.$path;
            if (! is_dir($dir)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (! $file->isFile() || in_array($file->getFilename(), $skip, true)) {
                    continue;
                }

                $name = $file->getFilename();
                if (! collect(self::CODE_EXTENSIONS)->contains(fn ($ext) => str_ends_with($name, '.'.$ext))) {
                    continue;
                }

                foreach (@file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $n => $line) {
                    $haystack = mb_strtolower($line);
                    if ($exclude !== '' && $exclude !== $token) {
                        $haystack = str_replace($exclude, '', $haystack);
                    }
                    if (str_contains($haystack, $token)) {
                        $rel = str_replace($base.DIRECTORY_SEPARATOR, '', $file->getPathname());
                        $hits[] = $rel.':'.($n + 1).'  '.trim(mb_substr($line, 0, 120));
                    }
                }
            }
        }

        return $hits;
    }

    /**
     * A régi név nyomai az adatbázisban (a parancshoz — CI-barát). Az `$exclude`
     * (az új slug) előfordulásait kivágja, hogy pl. a `site_domain=roadsidephoto.eu`
     * ne legyen fals találat.
     *
     * @return list<string>
     */
    public function scanDatabase(string $token, string $exclude = ''): array
    {
        $token = mb_strtolower($token);
        $exclude = mb_strtolower($exclude);
        $like = '%'.$token.'%';
        $hits = [];

        $matches = function (string $value) use ($token, $exclude): bool {
            $h = mb_strtolower($value);
            if ($exclude !== '' && $exclude !== $token) {
                $h = str_replace($exclude, '', $h);
            }

            return str_contains($h, $token);
        };

        foreach (self::EMAIL_COLUMNS as $table => $column) {
            try {
                foreach (DB::table($table)->whereRaw("LOWER({$column}) LIKE ?", [$like])->limit(50)->pluck($column) as $v) {
                    if ($matches((string) $v)) {
                        $hits[] = "{$table}.{$column}: {$v}";
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        foreach (SiteSetting::query()->whereRaw('LOWER(value) LIKE ?', [$like])->pluck('value', 'key') as $key => $value) {
            if ($matches((string) $value)) {
                $hits[] = "site_settings.{$key}: ".mb_substr((string) $value, 0, 80);
            }
        }

        return $hits;
    }
}
