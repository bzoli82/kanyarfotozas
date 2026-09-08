<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\FtpImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A böngészőből **közvetlen R2-feltöltés** aláírt URL-jei. A fotós / admin a UI-ban
 * kiválasztja a fájlokat (vagy egy mappát), a böngésző egyenként, párhuzamosan,
 * folyamatjelzővel egyből az R2 „import" bucketbe tölti őket (rclone nélkül),
 * majd a `MediaImportController::store` az onnan-importot elindítja.
 *
 * Csak akkor működik, ha az import forrás-disk S3 (r2_import). SFTP / helyi disk
 * esetén a UI a webes feltöltésre esik vissza.
 */
class MediaUploadController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'avi'];

    private const MAX_BYTES = 512 * 1024 * 1024; // 512 MB / fájl

    public function __construct(private FtpImport $import) {}

    public function sign(Request $request, Event $event): JsonResponse
    {
        $user = $this->authorizeUpload($request);

        if (! $this->import->providesDirectUpload()) {
            return response()->json(['supported' => false]);
        }

        $data = $request->validate([
            'session' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'files' => ['required', 'array', 'min:1', 'max:250'],
            'files.*.path' => ['required', 'string', 'max:512'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_BYTES],
        ]);

        $scope = FtpImport::scopeForUser($user);
        $session = ($data['session'] ?? null) ?: now()->format('Ymd-His').'-'.Str::lower(Str::random(8));
        $prefix = self::UPLOAD_DIR($event->id, $session);

        $files = [];

        try {
            foreach ($data['files'] as $file) {
                $relative = $this->sanitizeRelativePath((string) $file['path']);

                if ($relative === null) {
                    continue;
                }

                $key = "{$prefix}/{$relative}";
                $signed = $this->import->signUpload($key, $scope);

                $files[] = [
                    'path' => $file['path'],
                    'key' => $key,
                    'url' => $signed['url'],
                    'headers' => $signed['headers'],
                ];
            }
        } catch (\Throwable $e) {
            report($e);

            // Az aláírás nem megy (pl. a disk mégsem támogatja) — a UI a webes feltöltésre vált.
            return response()->json(['supported' => false]);
        }

        if ($files === []) {
            return response()->json(['supported' => true, 'session' => $session, 'import_path' => $prefix, 'files' => []], 422);
        }

        return response()->json([
            'supported' => true,
            'session' => $session,
            'import_path' => $prefix, // ezt küldi a UI a /import hívás `paths`-ában
            'files' => $files,
        ]);
    }

    private static function UPLOAD_DIR(int $eventId, string $session): string
    {
        return FtpImport::UPLOAD_PREFIX."/{$eventId}/{$session}";
    }

    /**
     * A böngészőből kapott relatív útvonalat (fájlnév vagy mappa/alcmappa/fájlnév)
     * biztonságosra tisztítja: minden szegmens csak `[A-Za-z0-9._-]`, a kiterjesztés
     * engedélyezett, `..` tiltva. A fájlnév-törzs változatlan marad (preprocessed
     * videó-párosítás alapnév szerint megy).
     */
    private function sanitizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = [];

        foreach (explode('/', $path) as $seg) {
            $seg = trim($seg);

            if ($seg === '' || $seg === '.' || $seg === '..') {
                if ($seg === '..') {
                    return null;
                }

                continue;
            }

            $segments[] = preg_replace('/[^A-Za-z0-9._-]/', '_', $seg);
        }

        if ($segments === []) {
            return null;
        }

        $name = array_pop($segments);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($ext, self::ALLOWED, true)) {
            return null;
        }

        // Legfeljebb 2 mappa-mélység (alapnév-ütközés kerülése + preprocessed párosítás).
        $segments = array_slice($segments, -2);
        $segments[] = $name;

        return implode('/', $segments);
    }

    private function authorizeUpload(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || ($user->isPhotographer() && $user->is_active)), 403);

        return $user;
    }
}
