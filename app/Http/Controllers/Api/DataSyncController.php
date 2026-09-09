<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Az éles ↔ helyi szinkron FORRÁS végpontjai — a helyi fejlesztői gép hívja őket
 * a beállított titkos kulccsal (`Authorization: Bearer <token>`). Stateless
 * (routes/api.php), nincs session/CSRF.
 *
 * Rossz kulcs / kikapcsolt forrás = 404 (nem áruljuk el, hogy létezik a végpont).
 */
class DataSyncController extends Controller
{
    public function manifest(Request $request, DataSync $sync): JsonResponse
    {
        $this->authorizeToken($request, $sync);

        return response()->json($sync->manifest());
    }

    public function database(Request $request, DataSync $sync): BinaryFileResponse
    {
        $this->authorizeToken($request, $sync);

        $path = $sync->buildDump();
        $sync->recordPull((string) ($request->ip() ?? '?'));

        return response()
            ->download($path, 'kanyarfotozas-sync-'.now()->format('Y-m-d_His').'.sql.gz', [
                'Content-Type' => 'application/gzip',
            ])
            ->deleteFileAfterSend(true);
    }

    private function authorizeToken(Request $request, DataSync $sync): void
    {
        abort_unless($sync->sourceEnabled(), 404);
        abort_unless($sync->tokenMatches($request->bearerToken()), 404);
    }
}
