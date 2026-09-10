<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\FtpImport;
use App\Services\WatermarkSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Esemeny lista. Photographer azokat latja, amikhez mar van feltoltott mediaja,
     * plusz amiket o maga hozott letre (hogy utana fel is tudjon toltani).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim($request->string('search')->value()),
            'country_id' => $request->string('country_id')->value(),
            'photographer_id' => $request->string('photographer_id')->value(),
            'date_from' => $request->string('date_from')->value(),
            'date_to' => $request->string('date_to')->value(),
        ];

        $events = Event::query()
            ->with('country:id,code,name_hu,flag_emoji')
            ->withCount('media')
            ->when($user->isPhotographer(), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('media', fn ($m) => $m->where('photographer_id', $user->id))
                        ->orWhere('created_by', $user->id);
                });
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                // Szabad szoveges kereses: esemeny neve VAGY helyszin VAGY orszag neve.
                $term = '%'.$filters['search'].'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'ILIKE', $term)
                        ->orWhere('location', 'ILIKE', $term)
                        ->orWhereHas('country', fn ($c) => $c
                            ->where('name_hu', 'ILIKE', $term)
                            ->orWhere('name_en', 'ILIKE', $term));
                });
            })
            ->when($filters['country_id'] !== '', fn ($q) => $q->where('country_id', $filters['country_id']))
            ->when($filters['photographer_id'] !== '', fn ($q) => $q->whereHas(
                'media',
                fn ($m) => $m->where('photographer_id', $filters['photographer_id']),
            ))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('event_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('event_date', '<=', $filters['date_to']))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Event $event) => [
                ...$event->toArray(),
                'can_edit' => Gate::forUser($user)->allows('manage-event', $event),
            ]);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'filters' => $filters,
            'countries' => Country::query()->where('active', true)->orderBy('name_hu')->get(['id', 'name_hu', 'flag_emoji']),
            'photographers' => $user->isAdmin()
                ? User::query()->where('role', User::ROLE_PHOTOGRAPHER)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-event');

        $user = $request->user();

        return Inertia::render('Admin/Events/Form', [
            'countries' => Country::query()->where('active', true)->orderBy('name_hu')->get(['id', 'code', 'name_hu', 'flag_emoji']),
            'event' => null,
            'basePrice' => (int) SiteSetting::get('base_price_huf', 1490),
            'photographers' => $user->isAdmin()
                ? User::query()->where('role', User::ROLE_PHOTOGRAPHER)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'organizers' => $user->isAdmin() ? $this->organizerOptions() : [],
            'ftpImport' => $this->ftpImportProp($user),
            'videoMode' => config('media.video_mode'),
            'watermarkText' => app(WatermarkSettings::class)->text(),
        ]);
    }

    /**
     * A „Beolvasás tárolóból" panel propja — admin ÉS aktív fotós is használhatja
     * (a fotós a saját, elkülönített almappáját).
     *
     * @return array{available: bool, scope: string|null}|null
     */
    private function ftpImportProp(User $user): ?array
    {
        if (! $user->isAdmin() && ! ($user->isPhotographer() && $user->is_active)) {
            return null;
        }

        $import = app(FtpImport::class);

        return [
            'available' => $import->isAvailable(),
            'direct_upload' => $import->providesDirectUpload(),
            'scope' => $user->isAdmin() ? null : FtpImport::scopeForUser($user),
        ];
    }

    public function store(StoreEventRequest $request, FtpImport $ftpImport): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Szervező-hozzárendelést csak admin adhat meg.
        if (! $user->isAdmin()) {
            $data = Arr::except($data, ['organizer_id', 'organizer_share_percent']);
        }

        $event = Event::create([
            ...Arr::except($data, ['import_paths', 'import_photographer_id']),
            'created_by' => $user->id,
        ]);

        $message = 'Esemény létrehozva.';

        // Opcionalis: a kivalasztott tavoli fajlok importalasa mindjart letrehozaskor.
        // Admin barmely fotos neveben, a tarolo gyokerebol; aktiv fotos a sajat
        // neveben, a sajat almappajabol (scope).
        $importPhotographerId = $user->isAdmin() ? ($data['import_photographer_id'] ?? null) : $user->id;
        $importScope = FtpImport::scopeForUser($user);

        if (! empty($data['import_paths']) && filled($importPhotographerId)) {
            if ($ftpImport->isAvailable()) {
                try {
                    $result = $ftpImport->import($event, $data['import_paths'], $importPhotographerId, $importScope);

                    activity()->performedOn($event)->causedBy($user)
                        ->log("Import (létrehozáskor): {$result['imported']} média importálva".($result['skipped'] > 0 ? ", {$result['skipped']} kihagyva" : ''));

                    $message .= " {$result['imported']} média importálva a tárolóból — a feldolgozás elindult.";

                    if (count($result['failed']) > 0) {
                        $message .= ' '.count($result['failed']).' fájlt nem sikerült beolvasni.';
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $message .= ' Az import viszont nem sikerült: '.$e->getMessage();
                }
            } else {
                $message .= ' Az import kimaradt: a forrás-tároló nincs beállítva.';
            }
        }

        return redirect()->route('admin.events.show', $event)->with('success', $message);
    }

    public function show(Request $request, Event $event): Response
    {
        $user = $request->user();

        $isOwnEvent = $event->created_by === $user->id;
        $hasOwnMedia = $event->media()->where('photographer_id', $user->id)->exists();

        if ($user->isPhotographer() && ! $isOwnEvent && ! $hasOwnMedia) {
            abort(403);
        }

        $mediaQuery = $event->media()->with('photographer:id,name')->orderByDesc('shot_at');

        if ($user->isPhotographer()) {
            $mediaQuery->where('photographer_id', $user->id);
        }

        return Inertia::render('Admin/Events/Show', [
            'event' => $event->load('country:id,code,name_hu,flag_emoji'),
            'media' => $mediaQuery->paginate(48)->withQueryString(),
            'countries' => Country::query()->where('active', true)->orderBy('name_hu')->get(['id', 'code', 'name_hu', 'flag_emoji']),
            'basePrice' => (int) SiteSetting::get('base_price_huf', 1490),
            'photographers' => $user->isAdmin()
                ? User::query()->where('role', User::ROLE_PHOTOGRAPHER)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'organizers' => $user->isAdmin() ? $this->organizerOptions() : [],
            'isAdmin' => $user->isAdmin(),
            'canEditEvent' => Gate::forUser($user)->allows('manage-event', $event),
            'ftpImport' => $this->ftpImportProp($user),
            'videoMode' => config('media.video_mode'),
            'watermarkText' => app(WatermarkSettings::class)->text(),
        ]);
    }

    /**
     * @return Collection<int, array{id: string, name: string}>
     */
    private function organizerOptions()
    {
        return User::query()
            ->where('role', User::ROLE_ORGANIZER)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->user()->isAdmin()) {
            $data = Arr::except($data, ['organizer_id', 'organizer_share_percent']);
        }

        $event->update($data);

        // Az esemeny-szintu ar valtozasakor a mar feltoltott mediak ara is atall
        // (a mar megvasarolt tetelek ara valtozatlan — az `order_media`-ban rogzul).
        $repriced = 0;

        if ($event->wasChanged('photo_price_cents')) {
            $repriced += $event->media()->where('type', Media::TYPE_PHOTO)->update(['price_cents' => $event->priceFor(Media::TYPE_PHOTO)]);
        }

        if ($event->wasChanged('video_price_cents')) {
            $repriced += $event->media()->where('type', Media::TYPE_VIDEO)->update(['price_cents' => $event->priceFor(Media::TYPE_VIDEO)]);
        }

        $message = 'Esemény frissítve.';

        if ($repriced > 0) {
            $message .= " {$repriced} médiát átáraztunk az új esemény-ár szerint.";
        }

        return redirect()->route('admin.events.show', $event)->with('success', $message);
    }

    /**
     * Fedőkép beállítása — a listaoldalak / térkép / megosztás borítója. A
     * `null` visszaállítja az alapértelmezettre (első kész média).
     */
    public function setCover(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        $data = $request->validate([
            'cover_media_id' => [
                'nullable',
                Rule::exists('media', 'id')->where('event_id', $event->id),
            ],
        ]);

        $event->update(['cover_media_id' => $data['cover_media_id'] ?? null]);

        return back()->with('success', $data['cover_media_id'] ?? null
            ? 'Fedőkép beállítva.'
            : 'Fedőkép visszaállítva az alapértelmezettre.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        if ($event->media()->exists()) {
            return back()->with('error', 'Az esemény nem törölhető, mert van hozzá feltöltött média.');
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Esemény törölve.');
    }
}
