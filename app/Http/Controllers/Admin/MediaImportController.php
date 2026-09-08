<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\FtpImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kézi kép-import a távoli fájlszerverről (SFTP `nas` disk) egy eseményhez.
 * Csak admin/superadmin — a fotósok a sima webes feltöltést használják, a
 * megosztott fájlszerver böngészése nekik nem elérhető.
 */
class MediaImportController extends Controller
{
    public function __construct(private FtpImport $import) {}

    /**
     * Egy távoli mappa tartalmának listázása (JSON — a Vue fájlböngésző kéri le).
     * Eseménytől független: az új-esemény űrlapon és a részletnézetben is ez szolgál ki.
     */
    public function browse(Request $request): JsonResponse
    {
        $this->authorizeImport($request);

        if (! $this->import->isAvailable()) {
            return response()->json([
                'available' => false,
                'message' => 'A távoli fájlszerver (FTP/NAS) kapcsolat még nincs beállítva — lásd Tárhely beállítások.',
            ]);
        }

        try {
            $listing = $this->import->browse($request->string('path')->value());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['available' => true, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['available' => true, 'error' => 'Nem sikerült kapcsolódni a fájlszerverhez: '.$e->getMessage()]);
        }

        return response()->json(['available' => true, ...$listing]);
    }

    /**
     * A kiválasztott távoli képfájlok importálása az eseményhez.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeImport($request);

        $data = $request->validate([
            'photographer_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('role', User::ROLE_PHOTOGRAPHER)],
            'paths' => ['required', 'array', 'min:1', 'max:'.FtpImport::MAX_PER_IMPORT],
            'paths.*' => ['required', 'string', 'max:1024'],
        ]);

        if (! $this->import->isAvailable()) {
            return back()->with('error', 'A távoli fájlszerver (FTP/NAS) kapcsolat még nincs beállítva.');
        }

        try {
            $result = $this->import->import($event, $data['paths'], $data['photographer_id']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Az import nem sikerült: '.$e->getMessage());
        }

        activity()->performedOn($event)->causedBy($request->user())
            ->log("FTP import: {$result['imported']} kép importálva".($result['skipped'] > 0 ? ", {$result['skipped']} kihagyva" : ''));

        $message = "{$result['imported']} kép importálva — a feldolgozás elindult.";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} fájl már korábban be lett importálva, ezeket kihagytuk.";
        }

        if (count($result['failed']) > 0) {
            $message .= ' '.count($result['failed']).' fájlt nem sikerült beolvasni.';
        }

        return back()->with($result['imported'] > 0 ? 'success' : 'error', $message);
    }

    private function authorizeImport(Request $request): void
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);
    }
}
