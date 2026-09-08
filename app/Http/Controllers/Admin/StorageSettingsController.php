<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ArchiveMediaOriginalToNas;
use App\Models\Media;
use App\Services\NasConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StorageSettingsController extends Controller
{
    /**
     * NAS (SFTP) kapcsolati adatok + archivalasi hibak — kizarolag superadmin felulet,
     * a fotosok/feltoltok nem latjak (a route 'role:superadmin' vedett).
     */
    public function index(NasConnection $nasConnection): Response
    {
        return Inertia::render('Admin/Settings/Storage', [
            'configured' => $nasConnection->isConfigured(),
            'connection' => $nasConnection->settingsForForm(),
            'stats' => [
                'local' => Media::query()->where('original_storage', Media::STORAGE_LOCAL)->count(),
                'nas' => Media::query()->where('original_storage', Media::STORAGE_NAS)->count(),
                'failing' => Media::query()->whereNotNull('archive_error')->count(),
            ],
            'failingMedia' => Media::query()
                ->whereNotNull('archive_error')
                ->with(['event:id,name,location', 'photographer:id,name'])
                ->orderByDesc('archive_attempts')
                ->limit(50)
                ->get(['id', 'event_id', 'photographer_id', 'type', 'archive_attempts', 'archive_error', 'updated_at']),
        ]);
    }

    /**
     * A NAS kapcsolati adatok elmentese az admin formrol (site_settings tablaba,
     * titkositva) — nem kell tobbe .env-et szerkeszteni.
     */
    public function update(Request $request, NasConnection $nasConnection): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'root' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'private_key' => ['nullable', 'string'],
            'private_key_passphrase' => ['nullable', 'string'],
        ]);

        $nasConnection->updateSettings($data);

        return back()->with('success', 'NAS kapcsolati adatok elmentve.');
    }

    /**
     * Elo kapcsolat-teszt a NAS-hoz (nem tarolt allapot, minden hivaskor lefut).
     */
    public function test(NasConnection $nasConnection): RedirectResponse
    {
        if (! $nasConnection->isConfigured()) {
            return back()->with('error', 'A NAS kapcsolat még nincs beállítva.');
        }

        try {
            Storage::disk('nas')->directoryExists('.');

            return back()->with('success', 'Sikeres kapcsolódás a NAS-hoz.');
        } catch (Throwable $e) {
            return back()->with('error', 'Sikertelen kapcsolódás: '.$e->getMessage());
        }
    }

    /**
     * Egy hibas archivalas ujraprobalasa.
     */
    public function retry(Media $media): RedirectResponse
    {
        $media->update(['archive_attempts' => 0, 'archive_error' => null]);

        ArchiveMediaOriginalToNas::dispatch($media->id);

        return back()->with('success', 'Újrapróbálás elindítva.');
    }
}
