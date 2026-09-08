<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Services\ContactThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * A feltöltő fotós saját beérkezett üzenetei (a média-oldali „Kérdés a fotóshoz"
 * űrlapról) — megtekintés + válasz. Az adminok minden szálat látnak és a fotós
 * nevében is válaszolhatnak (ld. Admin\MessageController).
 */
class MessageController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $photographerId = $request->user()->id;

        $threads = ContactMessage::query()
            ->where('photographer_id', $photographerId)
            ->with('replies')
            ->withCount('replies')
            ->orderByRaw('COALESCE(last_reply_at, created_at) DESC')
            ->limit(200)
            ->get()
            ->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'subject' => $m->subject,
                'status' => $m->status,
                'answered' => $m->replies_count > 0,
                'created_at' => $m->created_at?->toIso8601String(),
                'last_activity_at' => ($m->last_reply_at ?? $m->created_at)?->toIso8601String(),
            ]);

        $selected = null;
        if ($request->filled('thread')) {
            $message = ContactMessage::query()
                ->where('photographer_id', $photographerId)
                ->with(['event:id,name', 'replies.author:id,name', 'replies.onBehalfOf:id,name'])
                ->find($request->integer('thread'));

            if ($message) {
                $selected = [
                    'id' => $message->id,
                    'name' => $message->name,
                    'email' => $message->email,
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'status' => $message->status,
                    'event' => $message->event?->only(['id', 'name']),
                    'created_at' => $message->created_at?->toIso8601String(),
                    'replies' => $message->replies->map(fn (ContactReply $r) => [
                        'id' => $r->id,
                        'body' => $r->body,
                        'author' => $r->author?->name,
                        'on_behalf_of' => $r->onBehalfOf?->name,
                        'created_at' => $r->created_at?->toIso8601String(),
                    ]),
                ];
            }
        }

        return Inertia::render('Photographer/Messages/Index', [
            'threads' => $threads,
            'selected' => $selected,
        ]);
    }

    public function reply(Request $request, ContactMessage $message, ContactThread $thread): RedirectResponse
    {
        abort_unless((string) $message->photographer_id === (string) $request->user()->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread->reply($message, $data['body'], $request->user());

        return back()->with('success', 'Válasz elküldve.');
    }
}
