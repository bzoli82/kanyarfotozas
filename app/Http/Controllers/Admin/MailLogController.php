<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SentEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Kimenő e-mailek naplója (/admin/mail-log, superadmin) — „a vevő megkapta-e a
 * letöltő linket". A `LogSentEmail` listener tölti; csak sikeres küldés kerül be.
 * A régi sorokat napi ütemezett takarítás törli (30 nap).
 */
class MailLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $q = $request->string('q')->value() ?: null;

        $emails = SentEmail::query()
            ->when($q, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('recipient', 'ILIKE', "%{$q}%")
                ->orWhere('subject', 'ILIKE', "%{$q}%")))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SentEmail $m) => [
                'id' => $m->id,
                'recipient' => $m->recipient,
                'subject' => $m->subject,
                'mailable' => $m->mailable,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/MailLog', [
            'emails' => $emails,
            'filters' => ['q' => $q],
            'total' => SentEmail::query()->count(),
        ]);
    }
}
