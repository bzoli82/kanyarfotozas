<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrganizerPayout;
use App\Models\User;
use App\Services\OrganizerRevenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Az esemény-szervezőknek járó / kifizetett bevétel-részesedés adminfelülete
 * (superadmin). Egyszerű, kézi könyvelés: a felhalmozott részesedés számított,
 * a kifizetést az admin rögzíti egy sorral.
 */
class OrganizerPayoutController extends Controller
{
    public function index(OrganizerRevenue $revenue): Response
    {
        $organizers = User::query()
            ->where('role', User::ROLE_ORGANIZER)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $organizer) use ($revenue) {
                $totals = $revenue->totals($organizer);

                return [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'email' => $organizer->email,
                    'events' => $revenue->summary($organizer),
                    'accrued_cents' => $totals['share_cents'],
                    'paid_cents' => $totals['paid_cents'],
                    'outstanding_cents' => $totals['outstanding_cents'],
                ];
            });

        return Inertia::render('Admin/OrganizerPayouts/Index', [
            'organizers' => $organizers,
            'recent' => OrganizerPayout::query()
                ->with(['organizer:id,name', 'event:id,name'])
                ->orderByDesc('paid_at')
                ->limit(50)
                ->get()
                ->map(fn (OrganizerPayout $p) => [
                    'id' => $p->id,
                    'organizer' => $p->organizer?->name,
                    'event' => $p->event?->name,
                    'amount_cents' => $p->amount_cents,
                    'reference' => $p->reference,
                    'note' => $p->note,
                    'paid_at' => $p->paid_at?->toDateString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organizer_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('role', User::ROLE_ORGANIZER)],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['event_id'])) {
            abort_unless(
                Event::query()->whereKey($data['event_id'])->where('organizer_id', $data['organizer_id'])->exists(),
                422,
                'A kiválasztott esemény nem ehhez a szervezőhöz tartozik.',
            );
        }

        $payout = OrganizerPayout::create([
            'organizer_id' => $data['organizer_id'],
            'event_id' => $data['event_id'] ?? null,
            'amount_cents' => $data['amount_cents'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'paid_at' => filled($data['paid_at'] ?? null) ? Carbon::parse($data['paid_at']) : now(),
            'created_by' => $request->user()->id,
        ]);

        activity()->performedOn($payout)->causedBy($request->user())
            ->log("Szervező kifizetés rögzítve: {$payout->amount_cents} Ft");

        return back()->with('success', 'Kifizetés rögzítve.');
    }

    public function destroy(Request $request, OrganizerPayout $organizerPayout): RedirectResponse
    {
        $organizerPayout->delete();

        return back()->with('success', 'Kifizetés-tétel törölve.');
    }
}
