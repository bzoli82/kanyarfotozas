<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A fotós mobil PWA (EPIC-15) feltöltő oldala — /upload. Ugyanazt a
 * `POST /admin/events/{event}/media` végpontot használja, mint az asztali
 * felület (StoreMediaRequest), csak mobil-optimalizált UI-val, fájlonkénti
 * folyamatjelzéssel és offline sorral.
 */
class MobileUploadController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $events = Event::query()
            ->when($user->isPhotographer(), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('media', fn ($m) => $m->where('photographer_id', $user->id))
                        ->orWhere('created_by', $user->id);
                });
            })
            ->whereIn('status', [Event::STATUS_ANNOUNCED, Event::STATUS_LIVE, Event::STATUS_DRAFT])
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get(['id', 'name', 'location', 'status', 'event_date'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'location' => $event->location,
                'status' => $event->status,
                'date' => $event->event_date?->toDateString(),
            ]);

        return Inertia::render('MobileUpload', [
            'events' => $events,
            'isAdmin' => $user->isAdmin(),
            'photographers' => $user->isAdmin()
                ? User::query()->where('role', User::ROLE_PHOTOGRAPHER)->orderBy('name')->get(['id', 'name'])
                : [],
            'maxFileMb' => 500,
            'maxVideoSeconds' => 300,
        ]);
    }
}
