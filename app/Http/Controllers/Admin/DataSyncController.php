<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DataSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * „Éles ↔ helyi szinkron" (/admin/settings/data-sync) — csak superadmin.
 *
 *  - Éles példányon: a „szinkron-forrás" ki/be kapcsolása + a titkos kulcs.
 *  - Helyi példányon: az éles kapcsolat megadása + gombok az éles adatbázis és
 *    média letöltésére a helyi gépre. A letöltés/visszaállítás SOHA nem fut
 *    `production` környezetben.
 */
class DataSyncController extends Controller
{
    public function index(DataSync $sync): InertiaResponse
    {
        $isProduction = app()->isProduction();

        $target = null;

        if (! $isProduction) {
            $remote = $sync->remote();
            $connection = (string) config('database.default');

            $target = [
                'has_remote' => $remote !== null,
                'remote_url' => $remote['url'] ?? null,
                'db_name' => (string) config("database.connections.{$connection}.database"),
                'media_uses_shared_r2' => $sync->mediaUsesSharedR2(),
                'media_r2_configured' => $sync->mediaR2Configured(),
            ];
        }

        return Inertia::render('Admin/Settings/DataSync', [
            'isProduction' => $isProduction,
            'source' => [
                'enabled' => $sync->sourceEnabled(),
                'token' => $sync->sourceEnabled() ? $sync->token() : null,
                'last_pull' => $sync->lastPull(),
            ],
            'target' => $target,
        ]);
    }

    public function updateSource(Request $request, DataSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'regenerate' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['regenerate'])) {
            $sync->regenerateToken();
        }

        $sync->setSourceEnabled($data['enabled']);

        return back()->with('success', $data['enabled']
            ? 'Szinkron-forrás bekapcsolva. Másold a titkos kulcsot a helyi géped „Éles ↔ helyi szinkron" oldalára.'
            : 'Szinkron-forrás kikapcsolva — a helyi gépek nem tudják többé letölteni az adatokat.');
    }

    public function saveRemote(Request $request, DataSync $sync): RedirectResponse
    {
        $this->abortInProduction();

        $data = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'token' => ['required', 'string', 'min:20', 'max:200'],
        ]);

        $sync->setRemote($data['url'], $data['token']);

        return $this->connectionFlash($sync, 'Kapcsolat elmentve');
    }

    public function testRemote(DataSync $sync): RedirectResponse
    {
        $this->abortInProduction();

        return $this->connectionFlash($sync, 'Kapcsolat OK');
    }

    public function forgetRemote(DataSync $sync): RedirectResponse
    {
        $this->abortInProduction();

        $sync->forgetRemote();

        return back()->with('success', 'Az éles kapcsolat törölve a helyi gépről.');
    }

    public function pullDatabase(Request $request, DataSync $sync): RedirectResponse
    {
        $this->abortInProduction();

        $request->validate([
            'confirm' => ['required', Rule::in(['LETÖLTÖM', 'LETOLTOM'])],
            'scrub' => ['sometimes', 'boolean'],
        ]);

        $email = $request->user()->email;

        try {
            $result = $sync->pullDatabase($request->boolean('scrub', true));
        } catch (\Throwable $e) {
            return back()->with('error', 'A letöltés / visszaállítás hibára futott: '.$e->getMessage());
        }

        // A visszaállított adatbázis már az éles `sessions` táblát tartalmazza →
        // léptessük be újra ugyanazt a superadmint (ha az éles oldalon is létezik).
        $fresh = User::where('email', $email)->where('role', 'superadmin')->first();
        if ($fresh) {
            Auth::login($fresh);
            $request->session()->regenerate();
        }

        $message = 'Kész — az éles adatbázis visszaállítva a helyi gépre.';

        if (isset($result['orders_anonymised'])) {
            $message .= sprintf(
                ' Biztonságos másolat: %d titkos beállítás törölve, %d rendelés e-mail anonimizálva, a 2FA-titkok nullázva.',
                $result['secrets_deleted'] ?? 0,
                $result['orders_anonymised'] ?? 0,
            );
        }

        if (! $fresh) {
            $message .= ' Jelentkezz be újra (az éles oldalon más a superadmin fiók).';
        }

        return back()->with('success', $message);
    }

    public function pullMedia(Request $request, DataSync $sync): RedirectResponse
    {
        $this->abortInProduction();

        try {
            $result = $sync->pullMedia($request->boolean('with_originals'));
        } catch (\Throwable $e) {
            return back()->with('error', 'A média-másolás hibára futott: '.$e->getMessage());
        }

        $message = sprintf('%d fájl lemásolva, %d kihagyva (már megvolt).', $result['copied'], $result['skipped']);

        if ($result['more']) {
            $message .= ' Van még hátra — kattints újra a folytatáshoz.';
        }

        return back()->with('success', $message);
    }

    private function connectionFlash(DataSync $sync, string $prefix): RedirectResponse
    {
        try {
            $manifest = $sync->fetchRemoteManifest();
        } catch (\Throwable $e) {
            return back()->with('error', $prefix.', de a kapcsolat nem jött össze: '.$e->getMessage());
        }

        $counts = $manifest['counts'] ?? [];

        return back()->with('success', sprintf(
            '%s — éles adatok: %d esemény, %d média, %d rendelés, %d üzenet.',
            $prefix,
            $counts['events'] ?? 0,
            $counts['media'] ?? 0,
            $counts['orders'] ?? 0,
            $counts['messages'] ?? 0,
        ));
    }

    private function abortInProduction(): void
    {
        abort_if(app()->isProduction(), 403, 'Ez az oldal éles környezetben csak a forrás-kulcsot mutatja (a szinkron egyirányú: éles → helyi).');
    }
}
