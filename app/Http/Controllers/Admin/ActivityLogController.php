<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Globális tevékenység-napló (admin + superadmin): ki mit módosított — rendelés,
 * esemény, média, beállítás, fizetés-lezárás, visszatérítés stb. A Spatie
 * ActivityLog tölti (a `LogsActivity` modellek + az explicit `activity()->log()`
 * hívások). Csak leírás + tárgy + causer + időpont (a projekt nem tárol diff-et).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $q = $request->string('q')->value() ?: null;
        $subject = $request->string('subject')->value() ?: null;
        $causer = $request->string('causer')->value() ?: null;

        // A napló csak az elmúlt ~1 hónapot mutatja (a régebbieket a napi
        // `activitylog:clean` úgyis törli — ld. config/activitylog.php).
        $since = now()->subDays((int) config('activitylog.clean_after_days', 35));

        $base = fn () => Activity::query()
            ->where('created_at', '>=', $since)
            // A médiák nem naplózódnak (Media::$recordEvents = []) — a régi / demo
            // sorokat itt is kizárjuk, hogy ne fújják fel a listát.
            ->where(fn ($w) => $w->whereNull('subject_type')->orWhere('subject_type', 'NOT ILIKE', '%\\\\Media'));

        $activity = $base()
            ->with('causer')
            ->when($q, fn ($query) => $query->where('description', 'ILIKE', "%{$q}%"))
            ->when($subject, fn ($query) => $query->where('subject_type', 'ILIKE', '%\\\\'.$subject))
            ->when($causer, fn ($query) => $query->where('causer_id', $causer))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'subject_link' => $this->subjectLink($a),
                'causer' => $a->causer ? ['name' => $a->causer->name, 'email' => $a->causer->email] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        $subjectTypes = $base()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($t) => class_basename($t))
            ->unique()
            ->sort()
            ->values();

        $causers = User::query()
            ->whereIn('id', $base()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/ActivityLog', [
            'activity' => $activity,
            'filters' => ['q' => $q, 'subject' => $subject, 'causer' => $causer],
            'subjectTypes' => $subjectTypes,
            'causers' => $causers,
        ]);
    }

    private function subjectLink(Activity $a): ?string
    {
        if (! $a->subject_id) {
            return null;
        }

        return match ($a->subject_type ? class_basename($a->subject_type) : null) {
            'Order' => "/admin/orders/{$a->subject_id}",
            'Event' => "/admin/events/{$a->subject_id}",
            'User' => "/admin/photographers/{$a->subject_id}",
            default => null,
        };
    }
}
