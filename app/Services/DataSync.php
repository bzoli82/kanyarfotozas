<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Éles ↔ helyi adat-szinkron.
 *
 * KÉT szerep, egy osztály:
 *
 *  - FORRÁS (általában az éles szerver): bekapcsolható „szinkron-forrás" + egy
 *    titkos kulcs. Egy hitelesített HTTP hívásra friss `pg_dump`-ot ad ki
 *    (`/api/sync/*`). A dump `--inserts` formátumú, hogy tiszta PHP-ből (subprocess
 *    nélkül) vissza lehessen tölteni — így a helyi `php artisan serve` alatt is megy.
 *
 *  - CÉL (a helyi fejlesztői gép): eltárolja az éles URL-t + kulcsot egy
 *    gitignore-olt fájlban (a kulcs Crypt-titkosítva), és gombnyomásra letölti +
 *    visszaállítja az éles adatbázist, opcionálisan „biztonságos másolat" módban
 *    (titkok törlése + vásárlói e-mailek anonimizálása). A média külön gombbal
 *    másolható az R2-ről a helyi diszkre.
 *
 * A visszaállítás SOHA nem fut `production` környezetben (egyirányú: éles → helyi).
 */
class DataSync
{
    /** A biztonságos-másolat módban törölt `site_settings` kulcsok (titkok). */
    private const SECRET_SETTING_KEYS = [
        'stripe_secret', 'stripe_webhook_secret', 'stripe_publishable',
        'simplepay_secret_key', 'simplepay_merchant',
        'barion_pos_key', 'barion_payee',
        'invoice_api_key',
        'mail_password', 'mail_username', 'mail_host',
        'error_webhook_url', 'hcaptcha_secret',
        'plate_recognition_api_key',
        'r2_secret_access_key', 'r2_access_key_id', 'r2_endpoint',
        'web_scheduler_token', 'data_sync_token', 'data_sync_last_pull',
        'data_sync_source_enabled',
    ];

    /** Egy „média letöltése" kattintás alatt legfeljebb ennyi fájl (a többi újrakattintással). */
    private const MEDIA_BATCH_CAP = 5000;

    // =====================================================================
    // FORRÁS OLDAL
    // =====================================================================

    public function sourceEnabled(): bool
    {
        return (bool) SiteSetting::get('data_sync_source_enabled', false);
    }

    public function token(): string
    {
        $token = (string) SiteSetting::get('data_sync_token', '');

        if ($token === '') {
            SiteSetting::set('data_sync_token', $token = Str::lower(Str::random(48)));
        }

        return $token;
    }

    public function setSourceEnabled(bool $enabled): void
    {
        SiteSetting::set('data_sync_source_enabled', $enabled ? '1' : '0');

        if ($enabled) {
            $this->token();
        }
    }

    public function regenerateToken(): string
    {
        SiteSetting::set('data_sync_token', $token = Str::lower(Str::random(48)));

        return $token;
    }

    public function tokenMatches(?string $candidate): bool
    {
        return $candidate !== null && $candidate !== '' && hash_equals($this->token(), $candidate);
    }

    /**
     * @return array{generated_at: string, app_env: string, db_driver: string, counts: array<string, int>}
     */
    public function manifest(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app_env' => (string) app()->environment(),
            'db_driver' => (string) config('database.default'),
            'counts' => [
                'events' => $this->safeCount('events'),
                'media' => $this->safeCount('media'),
                'orders' => $this->safeCount('orders'),
                'messages' => $this->safeCount('contact_messages'),
                'users' => $this->safeCount('users'),
            ],
        ];
    }

    /**
     * Friss, gzippelt `pg_dump --inserts` — a hívó felelős a fájl törléséért.
     * (A forrás szerveren fut, ott a `pg_dump` elérhető.)
     */
    public function buildDump(): string
    {
        $connection = (string) config('database.default');
        $db = (array) config("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException("A szinkron csak PostgreSQL forrást támogat (jelenlegi: {$connection}).");
        }

        $binary = (string) config('monitoring.pg_dump_binary', 'pg_dump');

        $result = Process::forever()
            ->timeout((int) config('monitoring.backup_timeout', 600))
            ->env(['PGPASSWORD' => (string) ($db['password'] ?? '')])
            ->run([
                $binary,
                '--host='.($db['host'] ?? '127.0.0.1'),
                '--port='.($db['port'] ?? '5432'),
                '--username='.($db['username'] ?? 'postgres'),
                '--no-owner',
                '--no-privileges',
                '--no-comments',
                '--clean',
                '--if-exists',
                '--inserts',
                '--rows-per-insert=500',
                '--format=plain',
                '--dbname='.($db['database'] ?? ''),
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('pg_dump hiba: '.(trim($result->errorOutput() ?: $result->output()) ?: 'ismeretlen'));
        }

        $gz = gzencode($result->output(), 6);
        if ($gz === false) {
            throw new RuntimeException('A dump tömörítése nem sikerült.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kfsync');
        file_put_contents($tmp, $gz);

        return $tmp;
    }

    public function recordPull(string $ip): void
    {
        SiteSetting::set('data_sync_last_pull', json_encode([
            'at' => now()->toIso8601String(),
            'ip' => $ip,
        ]));
    }

    /**
     * @return array{at: string, ip: string}|null
     */
    public function lastPull(): ?array
    {
        $raw = SiteSetting::get('data_sync_last_pull');
        $data = $raw ? json_decode((string) $raw, true) : null;

        return is_array($data) ? $data : null;
    }

    // =====================================================================
    // CÉL OLDAL (helyi gép)
    // =====================================================================

    /** Az éles kapcsolat a `local` disken, gitignore-olt útvonalon (a token Crypt-titkosítva). */
    private const REMOTE_FILE = 'data-sync/remote.json';

    public function hasRemote(): bool
    {
        return $this->remote() !== null;
    }

    /**
     * @return array{url: string, token: string}|null
     */
    public function remote(): ?array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::REMOTE_FILE)) {
            return null;
        }

        $data = json_decode((string) $disk->get(self::REMOTE_FILE), true);

        if (! is_array($data) || empty($data['url']) || empty($data['token'])) {
            return null;
        }

        try {
            return ['url' => (string) $data['url'], 'token' => Crypt::decryptString($data['token'])];
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRemote(string $url, string $token): void
    {
        Storage::disk('local')->put(self::REMOTE_FILE, json_encode([
            'url' => rtrim(trim($url), '/'),
            'token' => Crypt::encryptString(trim($token)),
        ]));
    }

    public function forgetRemote(): void
    {
        Storage::disk('local')->delete(self::REMOTE_FILE);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchRemoteManifest(): array
    {
        $remote = $this->remote() ?? throw new RuntimeException('Nincs beállítva az éles kapcsolat.');

        $response = Http::baseUrl($remote['url'])
            ->withToken($remote['token'])
            ->acceptJson()
            ->timeout(20)
            ->get('/api/sync/manifest');

        if ($response->status() === 404) {
            throw new RuntimeException('Az éles oldalon nincs bekapcsolva a szinkron-forrás, vagy hibás a kulcs.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("Az éles szerver nem válaszolt rendben ({$response->status()}).");
        }

        return (array) $response->json();
    }

    /**
     * Az éles adatbázis letöltése + visszaállítása a helyi DB-be.
     *
     * @return array<string, int>
     */
    public function pullDatabase(bool $scrub): array
    {
        $this->assertNotProduction();

        $remote = $this->remote() ?? throw new RuntimeException('Nincs beállítva az éles kapcsolat.');

        @set_time_limit(900);
        @ignore_user_abort(true);

        $tmp = tempnam(sys_get_temp_dir(), 'kfsync');

        try {
            $response = Http::baseUrl($remote['url'])
                ->withToken($remote['token'])
                ->timeout(900)
                ->sink($tmp)
                ->get('/api/sync/database');

            if ($response->status() === 404) {
                throw new RuntimeException('Az éles oldalon nincs bekapcsolva a szinkron-forrás, vagy hibás a kulcs.');
            }

            if (! $response->successful()) {
                throw new RuntimeException("A letöltés nem sikerült ({$response->status()}).");
            }

            $sql = gzdecode((string) file_get_contents($tmp));
        } finally {
            @unlink($tmp);
        }

        if ($sql === false || $sql === '') {
            throw new RuntimeException('A letöltött fájl sérült (nem gzip).');
        }

        $this->restoreSql($sql);

        Artisan::call('migrate', ['--force' => true]);

        $result = $scrub ? $this->scrub() : [];

        Artisan::call('optimize:clear');

        return $result + ['restored' => 1];
    }

    /**
     * A letöltött `--inserts` dump lefuttatása a helyi kapcsolaton.
     * A dump `--clean --if-exists` — minden objektumot eldob, mielőtt újra létrehozná.
     */
    public function restoreSql(string $sql): void
    {
        $this->assertNotProduction();

        DB::connection()->getPdo()->exec($sql);
    }

    /**
     * „Biztonságos másolat": a titkos beállítások törlése + a személyes adatok
     * anonimizálása, hogy a helyi másolat fejlesztésre biztonságos legyen.
     *
     * @return array<string, int>
     */
    public function scrub(): array
    {
        $this->assertNotProduction();

        return DB::transaction(function (): array {
            $secrets = DB::table('site_settings')->whereIn('key', self::SECRET_SETTING_KEYS)->delete();

            $twoFactor = 0;
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $twoFactor = DB::table('users')->update([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ]);
            }

            $orders = DB::table('orders')->update([
                'buyer_email' => DB::raw("concat('vevo+', id::text, '@pelda.helyi')"),
                'billing_name' => null,
                'billing_tax_number' => null,
                'billing_address' => null,
                'billing_zip' => null,
                'billing_city' => null,
            ]);

            if (Schema::hasTable('contact_messages')) {
                DB::table('contact_messages')->update([
                    'email' => DB::raw("concat('level+', id::text, '@pelda.helyi')"),
                    'name' => 'Névtelen',
                ]);
            }

            if (Schema::hasTable('event_subscriptions')) {
                DB::table('event_subscriptions')->update([
                    'email' => DB::raw("concat('felirat+', id::text, '@pelda.helyi')"),
                ]);
            }

            foreach (['purchase_otps', 'failed_login_attempts', 'download_fingerprints', 'data_requests', 'sessions', 'jobs', 'failed_jobs'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            return [
                'secrets_deleted' => (int) $secrets,
                'two_factor_reset' => (int) $twoFactor,
                'orders_anonymised' => (int) $orders,
            ];
        });
    }

    /**
     * A média fájlok másolása az éles R2 tárból a helyi diszkre. Kihagyja a már
     * meglévő (azonos méretű) fájlokat, így újrakattintással folytatható.
     *
     * @return array{copied: int, skipped: int, more: bool}
     */
    public function pullMedia(bool $withOriginals): array
    {
        $this->assertNotProduction();

        if (! $this->mediaR2Configured()) {
            throw new RuntimeException('Nincs R2 kulcs beállítva a helyi gépen. Add meg a Tárhely → „Cloudflare R2" szekcióban ugyanazokat a kulcsokat, mint élesen.');
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $copied = 0;
        $skipped = 0;
        $more = false;

        // [forrás R2 disk, cél helyi disk, kihagyandó prefix]
        $routes = [
            ['r2_public', config('media.disks.public', 'public'), null],
            ['r2_private', config('media.disks.archive', 'local'), $withOriginals ? null : 'originals/'],
        ];

        foreach ($routes as [$fromDisk, $toDisk, $skipPrefix]) {
            if ($fromDisk === $toDisk) {
                continue; // a helyi disk MÁR az R2 — nincs mit másolni
            }

            $source = Storage::disk($fromDisk);
            $target = Storage::disk($toDisk);

            foreach ($source->allFiles() as $file) {
                if ($skipPrefix !== null && str_starts_with($file, $skipPrefix)) {
                    continue;
                }

                if ($target->exists($file) && $target->size($file) === $source->size($file)) {
                    $skipped++;

                    continue;
                }

                $stream = $source->readStream($file);
                $target->writeStream($file, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (++$copied >= self::MEDIA_BATCH_CAP) {
                    $more = true;
                    break 2;
                }
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped, 'more' => $more];
    }

    public function mediaR2Configured(): bool
    {
        return filled(config('filesystems.disks.r2_public.key'))
            && filled(config('filesystems.disks.r2_public.secret'));
    }

    /**
     * A helyi média-disk MÁR az éles R2-t használja (közös tár → a média
     * automatikusan szinkronban van, nincs mit másolni).
     */
    public function mediaUsesSharedR2(): bool
    {
        return str_starts_with((string) config('media.disks.public', 'public'), 'r2');
    }

    private function assertNotProduction(): void
    {
        abort_if(app()->isProduction(), 403, 'Ez a művelet éles környezetben tiltott (a szinkron egyirányú: éles → helyi).');
    }

    private function safeCount(string $table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }
}
