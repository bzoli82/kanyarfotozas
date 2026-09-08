<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMediaChunk;
use App\Models\Event;
use App\Models\User;
use App\Services\FtpImport;
use App\Services\MediaImportProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;

/**
 * Tömeges média-import egy külső tárolóból (SFTP `nas` VAGY Cloudflare R2
 * `r2_import` „drop zone" VAGY helyi mappa — `config('media.import_disk')`).
 *
 * Admin/superadmin a tároló teljes gyökerét látja és bármely fotós nevében
 * importálhat. **Aktív fotós** is használhatja, de csak a saját, elkülönített
 * almappáját (`{media.import_photographer_folder}/{id}`) böngészheti, és mindig
 * a saját nevében importál.
 *
 * Kis köteg (≤ `media.import_inline_max`) azonnal fut; nagyobb a `imports`
 * queue-n, batch job-ban, a `status()` végpontról pollozható folyamatjelzővel.
 */
class MediaImportController extends Controller
{
    public function __construct(private FtpImport $import) {}

    public function browse(Request $request): JsonResponse
    {
        $user = $this->authorizeImport($request);

        if (! $this->import->isAvailable()) {
            return response()->json([
                'available' => false,
                'message' => 'A tömeges import forrás-tároló még nincs beállítva — lásd Tárhely beállítások.',
            ]);
        }

        try {
            $listing = $this->import->browse($request->string('path')->value(), $this->scopeFor($user));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['available' => true, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['available' => true, 'error' => 'Nem sikerült kapcsolódni a tárolóhoz: '.$e->getMessage()]);
        }

        return response()->json(['available' => true, ...$listing]);
    }

    public function store(Request $request, Event $event, MediaImportProgress $progress): RedirectResponse
    {
        $user = $this->authorizeImport($request);

        $data = $request->validate([
            'photographer_id' => [Rule::requiredIf($user->isAdmin()), 'uuid', Rule::exists('users', 'id')->where('role', User::ROLE_PHOTOGRAPHER)],
            'paths' => ['required', 'array', 'min:1', 'max:'.FtpImport::MAX_PER_IMPORT],
            'paths.*' => ['required', 'string', 'max:1024'],
        ]);

        // Fotós: mindig a saját nevében, a saját mappájából.
        $photographerId = $user->isAdmin() ? $data['photographer_id'] : $user->id;
        $scope = $this->scopeFor($user);

        if (! $this->import->isAvailable()) {
            return back()->with('error', 'A tömeges import forrás-tároló még nincs beállítva.');
        }

        $running = $progress->forEvent($event->id);
        if ($running && $running['running']) {
            return back()->with('error', 'Ehhez az eseményhez épp fut egy import — várd meg, amíg befejeződik.');
        }

        try {
            $plan = $this->import->planImport($data['paths'], $scope);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Az import-terv összeállítása nem sikerült: '.$e->getMessage());
        }

        $units = $plan['units'];
        $total = count($units);

        if ($total === 0) {
            return back()->with('error', 'A kijelölésben nincs importálható kép/videó.');
        }

        $hardCap = (int) config('media.import_hard_cap', 20000);
        if ($total > $hardCap) {
            return back()->with('error', "Túl sok fájl egyszerre ({$total}). Bontsd kisebb mappákra (max {$hardCap}).");
        }

        // Kis köteg: azonnal, hogy rögtön lásd az eredményt.
        if ($total <= (int) config('media.import_inline_max', 25)) {
            $imported = $skipped = $failed = 0;

            foreach ($units as $unit) {
                match ($this->import->importUnit($event, $unit, $photographerId)) {
                    'imported' => $imported++,
                    'skipped' => $skipped++,
                    default => $failed++,
                };
            }

            activity()->performedOn($event)->causedBy($user)
                ->log("Import: {$imported} média".($skipped ? ", {$skipped} kihagyva" : '').($failed ? ", {$failed} hiba" : ''));

            $msg = "{$imported} média importálva — a feldolgozás elindult.";
            if ($skipped) {
                $msg .= " {$skipped} kihagyva (már be volt importálva).";
            }
            if ($failed) {
                $msg .= " {$failed} fájlt nem sikerült beolvasni.";
            }

            return back()->with($imported > 0 ? 'success' : 'error', $msg);
        }

        // Nagy köteg: háttér-batch a `imports` queue-n.
        $chunkSize = max(10, (int) config('media.import_chunk_size', 100));
        $jobs = collect($units)
            ->chunk($chunkSize)
            ->map(fn ($chunk) => new ImportMediaChunk($event->id, $photographerId, $chunk->values()->all()))
            ->all();

        $batch = Bus::batch($jobs)
            ->name(MediaImportProgress::batchName($event->id))
            ->onQueue('imports')
            ->allowFailures()
            ->dispatch();

        $progress->start($batch->id, $total);

        activity()->performedOn($event)->causedBy($user)
            ->log("Import elindítva a háttérben: {$total} egység (".FtpImport::disk().' tárolóból)');

        return back()->with('success', "{$total} fájl importja elindult a háttérben — a média fokozatosan megjelenik lent (a folyamat itt látszik).");
    }

    public function status(Request $request, Event $event, MediaImportProgress $progress): JsonResponse
    {
        $this->authorizeImport($request);

        return response()->json($progress->forEvent($event->id) ?? ['running' => false, 'finished' => true, 'total' => 0]);
    }

    /** Az importra jogosult felhasználó (admin VAGY aktív fotós). */
    private function authorizeImport(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || ($user->isPhotographer() && $user->is_active)), 403);

        return $user;
    }

    /** A böngésző-gyökér: admin → a tároló gyökere; fotós → a saját almappája. */
    private function scopeFor(User $user): string
    {
        if ($user->isAdmin()) {
            return '';
        }

        return trim((string) config('media.import_photographer_folder', 'fotosok'), '/')."/{$user->id}";
    }
}
