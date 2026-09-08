<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\PersonalDataEraser;
use App\Services\PersonalDataExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * GDPR kérelmek feldolgozása — csak superadmin. A megerősített (verified)
 * kérelmeknél letölthető az adatkivonat (export) vagy elvégezhető a törlés/anonimizálás.
 */
class DataRequestController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/DataRequests/Index', [
            'requests' => DataRequest::query()
                ->orderByRaw("array_position(ARRAY['verified','pending','completed','rejected']::text[], status)")
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->map(fn (DataRequest $r) => [
                    'id' => $r->id,
                    'email' => $r->email,
                    'type' => $r->type,
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'verified_at' => $r->verified_at?->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                    'note' => $r->note,
                ]),
        ]);
    }

    public function download(DataRequest $dataRequest, PersonalDataExporter $exporter): Response
    {
        abort_unless($dataRequest->type === DataRequest::TYPE_EXPORT && $dataRequest->isVerified(), 422);

        $payload = json_encode($exporter->forEmail($dataRequest->email), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="adatkivonat-'.$dataRequest->id.'.json"',
        ]);
    }

    public function complete(Request $request, DataRequest $dataRequest, PersonalDataEraser $eraser): RedirectResponse
    {
        abort_unless($dataRequest->isVerified(), 422);

        $note = $request->string('note')->limit(240)->value();

        if ($dataRequest->type === DataRequest::TYPE_DELETE) {
            $result = $eraser->eraseEmail($dataRequest->email);
            $note = trim($note.' — '.collect($result)->map(fn ($v, $k) => "{$k}: {$v}")->join(', '), ' —');
        }

        $dataRequest->update([
            'status' => DataRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'handled_by' => $request->user()->id,
            'note' => $note ?: null,
        ]);

        return back()->with('success', 'A kérelem feldolgozva.');
    }

    public function reject(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $dataRequest->update([
            'status' => DataRequest::STATUS_REJECTED,
            'handled_by' => $request->user()->id,
            'note' => $request->string('note')->limit(240)->value() ?: 'Elutasítva.',
        ]);

        return back()->with('success', 'A kérelem elutasítva.');
    }
}
