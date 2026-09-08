<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\DataRequestVerifyMail;
use App\Models\DataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GDPR adatkiadási / törlési kérelem (publikus). A látogató e-mail címére küldött
 * hivatkozással igazolja a kérést; utána a superadmin dolgozza fel (/admin/data-requests).
 */
class DataRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Info/DataRequest');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot — botnak ne adjunk visszajelzést.
        if (filled($request->input('website'))) {
            return back()->with('success', 'Elküldtük a megerősítő e-mailt.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'type' => ['required', Rule::in([DataRequest::TYPE_EXPORT, DataRequest::TYPE_DELETE])],
        ]);

        $dataRequest = DataRequest::create([
            'email' => mb_strtolower($data['email']),
            'type' => $data['type'],
            'status' => DataRequest::STATUS_PENDING,
        ]);

        Mail::to($dataRequest->email)->send(new DataRequestVerifyMail($dataRequest));

        return back()->with('success', 'Elküldtük a megerősítő e-mailt. A kérés csak a benne lévő hivatkozásra kattintás után indul el.');
    }

    public function verify(string $token): Response
    {
        $dataRequest = DataRequest::query()->where('token', $token)->firstOrFail();

        if ($dataRequest->status === DataRequest::STATUS_PENDING) {
            $dataRequest->update([
                'status' => DataRequest::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);
        }

        return Inertia::render('Info/DataRequestVerified', [
            'type' => $dataRequest->type,
            'alreadyHandled' => in_array($dataRequest->status, [DataRequest::STATUS_COMPLETED, DataRequest::STATUS_REJECTED], true),
        ]);
    }
}
