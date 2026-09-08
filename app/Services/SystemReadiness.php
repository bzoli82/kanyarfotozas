<?php

namespace App\Services;

use App\Console\Commands\SchedulerHeartbeat;
use App\Models\ErrorEvent;
use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Az oldal működéséhez KRITIKUS beállítások / szolgáltatások állapota egy helyen
 * (/admin/settings/critical). Csoportonként visszaad OK / figyelmeztetés / hiba
 * jelzéseket + hova kell kattintani a javításhoz.
 */
class SystemReadiness
{
    private const OK = 'ok';

    private const WARNING = 'warning';

    private const CRITICAL = 'critical';

    public function __construct(
        private PaymentSettings $payments,
        private NasConnection $nas,
    ) {}

    /**
     * @return list<array{group: string, summary: string, items: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        $groups = [
            $this->payments(),
            $this->storage(),
            $this->email(),
            $this->queueAndScheduler(),
            $this->mediaProcessing(),
            $this->monitoringAndBackup(),
            $this->coreSystem(),
        ];

        return array_map(function (array $group) {
            $statuses = array_column($group['items'], 'status');
            $group['summary'] = in_array(self::CRITICAL, $statuses, true)
                ? self::CRITICAL
                : (in_array(self::WARNING, $statuses, true) ? self::WARNING : self::OK);

            return $group;
        }, $groups);
    }

    /** Van-e egyáltalán működésképtelen kritikus dolog. */
    public function hasCriticalIssue(): bool
    {
        foreach ($this->groups() as $group) {
            if ($group['summary'] === self::CRITICAL) {
                return true;
            }
        }

        return false;
    }

    private function payments(): array
    {
        $production = app()->environment('production');
        $stripe = $this->payments->stripeConfigured();
        $simplePay = $this->payments->simplePayConfigured();
        $barion = $this->payments->barionConfigured();

        $items = [];

        if (! $stripe && ! $simplePay && ! $barion) {
            $items[] = $this->item('any', 'Legalább egy fizetési szolgáltató', self::CRITICAL,
                'Nincs beállítva egyetlen fizetési szolgáltató sem — a vásárlók nem tudnak fizetni.');
        } else {
            $items[] = $this->item('any', 'Legalább egy fizetési szolgáltató', self::OK,
                'Aktív: '.implode(', ', array_filter([$stripe ? 'Stripe' : null, $simplePay ? 'SimplePay' : null, $barion ? 'Barion' : null])).'.');
        }

        $items[] = $this->item('stripe', 'Stripe', $stripe ? self::OK : self::WARNING,
            $stripe ? 'Titkos kulcs beállítva.' : 'Nincs Stripe titkos kulcs (secret).');

        if ($stripe) {
            $items[] = $this->item('stripe_webhook', 'Stripe webhook aláírás', $this->payments->stripeWebhookReady() ? self::OK : self::WARNING,
                $this->payments->stripeWebhookReady()
                    ? 'A webhook secret be van állítva.'
                    : 'Nincs webhook secret — a fizetés a sikeres-oldalon lezárul, de a biztos aszinkron megerősítés hiányzik.');
        }

        $items[] = $this->item('simplepay', 'SimplePay', $simplePay ? self::OK : self::WARNING,
            $simplePay ? 'Merchant + secret key beállítva.' : 'Nincs SimplePay merchant / secret key.');

        $items[] = $this->item('barion', 'Barion', $barion ? self::OK : self::WARNING,
            $barion ? 'POSKey + kifizetési e-mail beállítva.' : 'Nincs Barion POSKey / kifizetési e-mail.');

        $invoicing = app(InvoiceSettings::class);
        $items[] = $this->item('invoicing', 'Számlázás', $invoicing->isConfigured() ? self::OK : self::WARNING,
            $invoicing->isConfigured()
                ? 'Billingo beállítva'.($invoicing->autoIssue() ? ' (automatikus számla)' : ' — az automatikus számla KI van kapcsolva').'.'
                : 'Nincs számlázó beállítva — magyar webshopnál a kártyás eladásról számla kötelező (nyugta nem elég).');

        if ($production) {
            if ($simplePay && (bool) config('services.simplepay.sandbox')) {
                $items[] = $this->item('simplepay_mode', 'SimplePay üzemmód', self::WARNING,
                    'Éles környezet, de a SimplePay SANDBOX módban van.');
            }
            if ($barion && (bool) config('services.barion.sandbox')) {
                $items[] = $this->item('barion_mode', 'Barion üzemmód', self::WARNING,
                    'Éles környezet, de a Barion SANDBOX módban van.');
            }
        }

        return [
            'group' => 'Fizetés',
            'summary' => self::OK,
            'items' => $items,
            'action' => ['label' => 'Fizetés beállítása', 'href' => '#payments'],
        ];
    }

    private function storage(): array
    {
        $items = [];

        $publicDisk = MediaStorage::public();
        $archiveDisk = MediaStorage::archive();

        $items[] = $this->item('public_disk', 'Publikus fájl disk', self::OK,
            "Aktív: {$publicDisk} (thumbnail / vízjeles előnézet / HLS / hero).");

        // public/storage symlink csak a lokalis 'public' disknel szamit.
        // A realpath-egyezes a legmegbizhatobb ellenorzes (a Windows + git-bash
        // symlinket a PHP is_link()/is_dir() nem mindig ismeri fel, de a link jo).
        if ($publicDisk === 'public') {
            $link = public_path('storage');
            $linked = @realpath($link) === @realpath(storage_path('app/public'))
                || is_dir($link)
                || is_link($link);
            $items[] = $this->item('storage_link', 'public/storage symlink', $linked ? self::OK : self::CRITICAL,
                $linked ? 'Létezik.' : 'Hiányzik — futtasd: php artisan storage:link (különben a képek 404-esek).');
        }

        if ($archiveDisk === 'nas') {
            $configured = $this->nas->isConfigured();
            $items[] = $this->item('nas', 'NAS (SFTP) kapcsolat a nagy fájlokhoz', $configured ? self::OK : self::WARNING,
                $configured
                    ? 'Kapcsolati adatok beállítva (kapcsolat-teszt a Tárhely oldalon).'
                    : 'Nincs NAS kapcsolat — az eredeti + letölthető fájlok a webhosting tárhelyét fogyasztják. (Ha R2-t használsz, állítsd: MEDIA_ARCHIVE_DISK=r2_private.)');
        } elseif (str_starts_with($archiveDisk, 'r2')) {
            $r2 = filled(config('filesystems.disks.r2_private.key')) && filled(config('filesystems.disks.r2_private.bucket'));
            $items[] = $this->item('r2', 'Cloudflare R2 (archív)', $r2 ? self::OK : self::CRITICAL,
                $r2 ? 'R2 kulcsok + bucket beállítva.' : 'Az archív disk R2, de hiányoznak az R2 kulcsok / bucket — add meg a Tárhely oldalon vagy a .env-ben.');
        } else {
            $items[] = $this->item('archive', 'Archív tároló', self::WARNING,
                'Nincs külön archív réteg — a nagy fájlok a lokális diskon maradnak.');
        }

        if (str_starts_with($publicDisk, 'r2')) {
            $r2pub = filled(config('filesystems.disks.r2_public.key')) && filled(config('filesystems.disks.r2_public.url'));
            $items[] = $this->item('r2_public', 'Cloudflare R2 (publikus)', $r2pub ? self::OK : self::CRITICAL,
                $r2pub ? 'R2 publikus kulcs + domain (R2_PUBLIC_URL) beállítva.' : 'Hiányoznak az R2 publikus kulcsok / a publikus domain — Tárhely oldal vagy .env.');
        }

        // Tömeges import / böngésző→R2 közvetlen feltöltés forrás-diskje.
        $importDisk = FtpImport::disk();
        if (str_starts_with($importDisk, 'r2')) {
            $direct = app(FtpImport::class)->providesDirectUpload();
            $items[] = $this->item('import_disk', 'Nagy feltöltés (böngésző → R2)', $direct ? self::OK : self::WARNING,
                $direct
                    ? 'Az import-tároló ('.$importDisk.') támogatja a közvetlen feltöltést. Ne feledd a CORS-szabályt az import bucketen (ld. Deploy-emlékeztetők).'
                    : 'Az import-disk R2, de a közvetlen feltöltés nem elérhető — ellenőrizd az R2 kulcsokat.');
        }

        return [
            'group' => 'Tárhely',
            'summary' => self::OK,
            'items' => $items,
            'action' => ['label' => 'Tárhely (R2 / NAS)', 'href' => '/admin/settings/storage'],
        ];
    }

    private function email(): array
    {
        $mailer = (string) config('mail.default');
        $production = app()->environment('production');
        $items = [];

        $realMailer = ! in_array($mailer, ['log', 'array'], true);

        $items[] = $this->item('mailer', 'E-mail küldő (mailer)',
            $realMailer ? self::OK : ($production ? self::CRITICAL : self::WARNING),
            $realMailer
                ? "Aktív: {$mailer}."
                : "Jelenleg: {$mailer} — a rendszer NEM küld valódi e-mailt (a visszaigazolók a logba kerülnek).");

        $from = (string) config('mail.from.address');
        $items[] = $this->item('from', 'Feladó cím', filled($from) && $from !== 'hello@example.com' ? self::OK : self::WARNING,
            filled($from) ? "Feladó: {$from}" : 'Nincs feladó cím beállítva.');

        return [
            'group' => 'E-mail',
            'summary' => self::OK,
            'items' => $items,
            'action' => ['label' => 'E-mail beállítása', 'href' => '/admin/settings/critical#mail'],
        ];
    }

    private function queueAndScheduler(): array
    {
        $items = [];

        $connection = (string) config('queue.default');
        $items[] = $this->item('queue', 'Várólista (queue)', $connection === 'sync' ? self::WARNING : self::OK,
            $connection === 'sync'
                ? 'sync — a feltöltés-feldolgozás a kérés alatt fut (lassú, időtúlléphet). Élesben: database + queue:work.'
                : "Kapcsolat: {$connection}. A szerveren futnia kell: php artisan queue:work --queue=videos,default");

        $failed = $this->tableCount('failed_jobs');
        if ($failed !== null) {
            $items[] = $this->item('failed_jobs', 'Sikertelen háttérfeladatok', $failed === 0 ? self::OK : self::WARNING,
                $failed === 0 ? 'Nincs sikertelen feladat.' : "{$failed} sikertelen feladat — nézd meg: php artisan queue:failed");
        }

        $last = SiteSetting::get(SchedulerHeartbeat::KEY);
        if ($last === null) {
            $items[] = $this->item('scheduler', 'Ütemező (cron)', self::WARNING,
                'Még nem futott le az ütemező — állítsd be a szerveren (`* * * * * php artisan schedule:run`), vagy kapcsold be a „Webes ütemezőt" a Deploy-emlékeztetőknél.');
        } else {
            $age = abs(Carbon::parse($last)->diffInMinutes(now()));
            $items[] = $this->item('scheduler', 'Ütemező (cron)', $age <= 20 ? self::OK : self::CRITICAL,
                $age <= 20
                    ? 'Utolsó futás: '.Carbon::parse($last)->diffForHumans()
                    : 'Az ütemező '.Carbon::parse($last)->diffForHumans().' óta nem futott — a cron valószínűleg nem fut (vagy a webes ütemezőt hívó szolgáltatás áll).');
        }

        return ['group' => 'Várólista & ütemezés', 'summary' => self::OK, 'items' => $items];
    }

    private function mediaProcessing(): array
    {
        $items = [];

        if (config('media.video_mode') === 'pipeline') {
            foreach (['ffmpeg' => config('media.ffmpeg_binary'), 'ffprobe' => config('media.ffprobe_binary')] as $name => $binary) {
                [$status, $detail] = $this->binaryStatus((string) $binary);
                $items[] = $this->item($name, mb_strtoupper($name), $status, $detail);
            }
        } else {
            $items[] = $this->item('video_mode', 'Videó-mód', self::OK,
                'Elő-feldolgozott mód (MEDIA_VIDEO_MODE=preprocessed): a fotós kódolja a videót, a szervernek nincs szüksége FFmpeg-re.');
        }

        $failedMedia = Media::query()->where('status', Media::STATUS_FAILED)->count();
        $items[] = $this->item('failed_media', 'Hibás média', $failedMedia === 0 ? self::OK : self::WARNING,
            $failedMedia === 0 ? 'Nincs hibás feldolgozás.' : "{$failedMedia} hibás média — a dashboard Médiaegészség paneljén újrafeldolgozható.");

        return [
            'group' => 'Médiafeldolgozás',
            'summary' => self::OK,
            'items' => $items,
            'action' => ['label' => 'Rendszámfelismerés', 'href' => '/admin/settings/plate-recognition'],
        ];
    }

    private function monitoringAndBackup(): array
    {
        $items = [];

        $openErrors = ErrorEvent::query()
            ->whereNull('resolved_at')
            ->where('last_seen_at', '>=', now()->subDay())
            ->count();

        $items[] = $this->item('errors', 'Kezeletlen hibák (24 óra)', $openErrors === 0 ? self::OK : self::WARNING,
            $openErrors === 0
                ? 'Nincs nyitott hiba az elmúlt 24 órában.'
                : "{$openErrors} nyitott hibacsoport — nézd meg: /admin/errors");

        $last = app(MonitoringSettings::class)->lastBackup();

        if ($last['at'] === null) {
            $items[] = $this->item('backup', 'Adatbázis-mentés', self::WARNING,
                'Még nem futott mentés — a napi `kanyarfotozas:backup` cron-nal fut (vagy indítsd kézzel lent).');
        } elseif ($last['status'] === 'failed') {
            $items[] = $this->item('backup', 'Adatbázis-mentés', self::CRITICAL,
                'A legutóbbi mentés HIBÁRA futott ('.$last['at']->diffForHumans().'): '.$last['error']);
        } else {
            $age = abs($last['at']->diffInHours(now()));
            $items[] = $this->item('backup', 'Adatbázis-mentés', $age <= 48 ? self::OK : self::WARNING,
                'Utolsó sikeres mentés: '.$last['at']->diffForHumans()
                .($age > 48 ? ' — több mint 48 órája, fut-e a cron?' : '.'));
        }

        return [
            'group' => 'Monitoring & mentés',
            'summary' => self::OK,
            'items' => $items,
            'action' => ['label' => 'Hibanapló', 'href' => '/admin/errors'],
        ];
    }

    private function coreSystem(): array
    {
        $production = app()->environment('production');
        $items = [];

        $items[] = $this->item('app_key', 'APP_KEY', filled(config('app.key')) ? self::OK : self::CRITICAL,
            filled(config('app.key')) ? 'Beállítva (a titkosított beállítások ehhez kötöttek).' : 'Hiányzik — php artisan key:generate');

        $url = (string) config('app.url');
        $items[] = $this->item('app_url', 'APP_URL', (! $production || str_starts_with($url, 'https://')) ? self::OK : self::CRITICAL,
            $production && ! str_starts_with($url, 'https://')
                ? "Éles környezet, de az APP_URL nem https: {$url} (a fizetési visszatérési URL-ek ebből képződnek)."
                : "Jelenleg: {$url}");

        if ($production) {
            $items[] = $this->item('debug', 'APP_DEBUG', config('app.debug') ? self::CRITICAL : self::OK,
                config('app.debug') ? 'Éles környezetben BE van kapcsolva a debug — kapcsold ki (APP_DEBUG=false).' : 'Ki van kapcsolva.');
        }

        $price = SiteSetting::get('base_price_huf');
        $items[] = $this->item('base_price', 'Alap médiaár', filled($price) ? self::OK : self::WARNING,
            filled($price) ? "Beállítva: {$price} Ft (ez az új események alap ára — eseményenként külön állítható fotóra és videóra)." : 'Nincs beállított alapár — 1490 Ft-tal számol.');

        return ['group' => 'Alaprendszer', 'summary' => self::OK, 'items' => $items];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function item(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    /**
     * @return array{0: string, 1: string} [status, detail]
     */
    private function binaryStatus(string $binary): array
    {
        if (blank($binary)) {
            return [self::CRITICAL, 'Nincs beállítva — add meg a .env-ben (FFMPEG_BINARY / FFPROBE_BINARY).'];
        }

        // Abszolút útvonal: fájl-ellenőrzés (gyors, megbízható, nem függ a web-process PATH-tól).
        if (preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $binary)) {
            return is_file($binary)
                ? [self::OK, "Elérhető: {$binary}"]
                : [self::CRITICAL, "Nem található a fájl: {$binary} — javítsd a .env útvonalat."];
        }

        // Csak parancsnév (PATH-ról): próbáljuk futtatni, de a bizonytalanság csak figyelmeztetés.
        try {
            $ok = Process::timeout(5)->run([$binary, '-version'])->successful();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok
            ? [self::OK, "Elérhető a PATH-on: {$binary}"]
            : [self::WARNING, "Nem sikerült futtatni ({$binary}) ebből a folyamatból — a queue worker környezetében ellenőrizd."];
    }

    private function tableCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
