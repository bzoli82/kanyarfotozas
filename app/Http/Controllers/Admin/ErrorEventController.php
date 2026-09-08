<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Hibanapló (superadmin): a kezeletlen kivételek ujjlenyomat szerint
 * csoportosítva, lezárható tételekkel. Az App\Services\ErrorReporter tölti.
 */
class ErrorEventController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'all'], true)
            ? $request->query('status')
            : 'open';

        $q = $request->string('q')->value() ?: null;

        $events = ErrorEvent::query()
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->when($q, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('exception_class', 'ILIKE', "%{$q}%")
                ->orWhere('message', 'ILIKE', "%{$q}%")
                ->orWhere('url', 'ILIKE', "%{$q}%")))
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ErrorEvent $e) => [
                'id' => $e->id,
                'exception_class' => $e->exception_class,
                'message' => $e->message,
                'location' => $e->file ? $e->file.':'.$e->line : null,
                'url' => $e->url,
                'method' => $e->method,
                'count' => $e->count,
                'first_seen_at' => $e->first_seen_at?->toIso8601String(),
                'last_seen_at' => $e->last_seen_at?->toIso8601String(),
                'resolved_at' => $e->resolved_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Errors/Index', [
            'events' => $events,
            'filters' => ['status' => $status, 'q' => $q],
            'openCount' => ErrorEvent::query()->whereNull('resolved_at')->count(),
        ]);
    }

    public function resolve(ErrorEvent $errorEvent): RedirectResponse
    {
        $errorEvent->update(['resolved_at' => now()]);

        return back()->with('success', 'Hiba lezárva.');
    }

    public function reopen(ErrorEvent $errorEvent): RedirectResponse
    {
        $errorEvent->update(['resolved_at' => null]);

        return back()->with('success', 'Hiba újranyitva.');
    }

    public function resolveAll(): RedirectResponse
    {
        ErrorEvent::query()->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return back()->with('success', 'Minden nyitott hiba lezárva.');
    }

    public function destroy(ErrorEvent $errorEvent): RedirectResponse
    {
        $errorEvent->delete();

        return back()->with('success', 'Hiba törölve.');
    }
}
