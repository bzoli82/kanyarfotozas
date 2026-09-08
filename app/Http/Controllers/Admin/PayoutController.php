<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotographerPayout;
use App\Models\User;
use App\Services\PhotographerPayoutService;
use App\Services\SiteBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotós kifizetés-elszámolás műveletei — a felület a Fotósok admin lapon van
 * (lista alatt a nyitott jutalék, fotósra kattintva a „Kifizetések" fül).
 */
class PayoutController extends Controller
{
    public function __construct(private PhotographerPayoutService $payouts) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_PHOTOGRAPHER, 404);

        $data = $request->validate([
            'until' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $payout = $this->payouts->createDraft(
                $user,
                filled($data['until'] ?? null) ? Carbon::parse($data['until'])->endOfDay() : null,
                $data,
            );
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        activity()->performedOn($user)->causedBy($request->user())
            ->log("Kifizetési bizonylat előkészítve: {$payout->payout_number} ({$payout->amount_cents} Ft)");

        return back()->with('success', "Bizonylat előkészítve: {$payout->payout_number}.");
    }

    public function markPaid(Request $request, PhotographerPayout $payout): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $this->payouts->markPaid($payout, $data);

        activity()->performedOn($payout->photographer)->causedBy($request->user())
            ->log("Kifizetés teljesítve: {$payout->payout_number} ({$payout->amount_cents} Ft, {$data['method']})");

        return back()->with('success', 'Kifizetés rögzítve.');
    }

    public function destroy(Request $request, PhotographerPayout $payout): RedirectResponse
    {
        try {
            $number = $payout->payout_number;
            $this->payouts->deleteDraft($payout);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        activity()->performedOn($payout->photographer)->causedBy($request->user())
            ->log("Kifizetési bizonylat visszavonva: {$number}");

        return back()->with('success', 'Bizonylat visszavonva — a tételek visszakerültek a nyitott egyenlegbe.');
    }

    public function export(User $user): StreamedResponse
    {
        abort_unless($user->role === User::ROLE_PHOTOGRAPHER, 404);

        $csv = $this->payouts->ledgerCsv($user);
        $slug = app(SiteBranding::class)->slug();
        $name = Str::slug($user->name) ?: $user->id;

        return response()->streamDownload(
            fn () => print ($csv),
            "{$slug}-kifizetes-{$name}-".now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
