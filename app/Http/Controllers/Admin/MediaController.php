<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaHasSalesException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Models\Event;
use App\Models\Media;
use App\Services\MediaDeleter;
use App\Services\MediaIngestor;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    /**
     * Batch feltoltes egy esemenyhez. Kepeknel a ProcessImageMedia (EPIC-04),
     * videoknal a ProcessVideoMedia (EPIC-05) job generalja a thumbnail/vizjelezett/
     * letoltheto valtozatokat, majd mindket esetben inditja a NAS-archivalast.
     */
    public function store(StoreMediaRequest $request, Event $event, MediaIngestor $ingestor): RedirectResponse
    {
        $user = $request->user();

        if ($user->isPhotographer() && ! $user->is_active) {
            abort(403);
        }

        $photographerId = $user->isAdmin() ? $request->input('photographer_id') : $user->id;

        $uploaded = 0;
        $duplicates = 0;

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $isVideo = in_array($extension, ['mp4', 'mov', 'avi'], true);
            $type = $isVideo ? Media::TYPE_VIDEO : Media::TYPE_PHOTO;

            $key = sprintf('originals/%d/%s.%s', $event->id, (string) Str::uuid(), $extension);
            Storage::disk(MediaStorage::STAGING)->putFileAs(
                dirname($key),
                $file,
                basename($key),
            );

            if ($ingestor->ingestStaged($event, $photographerId, $key, $type) !== null) {
                $uploaded++;
            } else {
                $duplicates++;
            }
        }

        $message = "Feltöltés elindult ({$uploaded} fájl) — a feldolgozás rövidesen elkészül.";

        if ($duplicates > 0) {
            $message .= " {$duplicates} fájlt kihagytunk, mert már fel van töltve ehhez az eseményhez.";
        }

        return back()->with($uploaded > 0 ? 'success' : 'error', $message);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeManage($request, $media);

        // Az ar mostantol esemeny-szintu (App\Models\Event::priceFor / EventController::update),
        // fajlonkent csak a lathatosag allithato.
        $data = $request->validate([
            'status' => ['required', Rule::in([Media::STATUS_READY, Media::STATUS_HIDDEN])],
        ]);

        // Csak kesz mediat lehet kezzel elrejteni/visszaallitani; feldolgozas alattit/hibasat nem.
        if (! in_array($media->status, [Media::STATUS_READY, Media::STATUS_HIDDEN], true)) {
            return back()->with('error', 'Csak kész média láthatósága módosítható.');
        }

        $media->update($data);

        return back()->with('success', 'Média frissítve.');
    }

    public function destroy(Request $request, Media $media, MediaDeleter $deleter): RedirectResponse
    {
        $this->authorizeManage($request, $media);

        try {
            $deleter->delete($media);
        } catch (MediaHasSalesException) {
            return back()->with('error', 'Ezt a médiát már megvásárolták — nem törölhető. Rejtsd el helyette (a vásárló így is le tudja tölteni).');
        }

        return back()->with('success', 'Média törölve.');
    }

    /**
     * Több média törlése egyszerre (a galéria-rácsban drag-kijelöléssel).
     * A `delete_ftp_source` esetén az FTP-importból származó eredeti fájlt is
     * törli a távoli szerverről.
     */
    public function bulkDestroy(Request $request, Event $event, MediaDeleter $deleter): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'delete_ftp_source' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $query = Media::query()->where('event_id', $event->id)->whereIn('id', $data['ids']);

        if (! $user->isAdmin()) {
            $query->where('photographer_id', $user->id);
        }

        $mediaItems = $query->get();
        $deleteSource = (bool) ($data['delete_ftp_source'] ?? false);
        $sourceDeleted = 0;
        $deleted = 0;
        $skippedSold = 0;

        foreach ($mediaItems as $media) {
            $withSource = $deleteSource && filled($media->import_source_path);

            try {
                $deleter->delete($media, $withSource);
            } catch (MediaHasSalesException) {
                $skippedSold++;

                continue;
            }

            $deleted++;

            if ($withSource) {
                $sourceDeleted++;
            }
        }

        if ($deleted === 0) {
            return back()->with('error', $skippedSold > 0
                ? "A kijelölt {$skippedSold} médiát már megvásárolták — nem törölhetők, csak elrejthetők."
                : 'A kijelöltek közül egyetlen média sem volt törölhető.');
        }

        $message = $deleted.' média törölve.';

        if ($skippedSold > 0) {
            $message .= " {$skippedSold} kihagyva (már megvásárolták).";
        }

        if ($sourceDeleted > 0) {
            $message .= " {$sourceDeleted} eredeti fájlt az FTP-szerverről is töröltünk.";
        }

        return back()->with('success', $message);
    }

    private function authorizeManage(Request $request, Media $media): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isPhotographer() && $media->photographer_id === $user->id) {
            return;
        }

        abort(403);
    }
}
