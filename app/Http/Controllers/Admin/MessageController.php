<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\User;
use App\Services\ContactThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Admin üzenetkezelő — MINDEN kapcsolati üzenetváltás (support + a feltöltő
 * fotósoknak címzett) egy helyen. Az admin bármelyik szálra válaszolhat, és a
 * fotósnak címzett üzeneteknél válaszolhat a fotós NEVÉBEN is, ha a fotós épp
 * nem elérhető.
 */
class MessageController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filter = $request->string('filter')->value(); // '', 'support', 'photographer', 'unanswered', 'resolved'

        $threads = ContactMessage::query()
            ->with(['photographer:id,name', 'replies'])
            ->withCount('replies')
            ->when($filter === 'support', fn ($q) => $q->whereNull('photographer_id'))
            ->when($filter === 'photographer', fn ($q) => $q->whereNotNull('photographer_id'))
            ->when($filter === 'unanswered', fn ($q) => $q->where('status', '!=', ContactMessage::STATUS_RESOLVED)->has('replies', '=', 0))
            ->when($filter === 'resolved', fn ($q) => $q->where('status', ContactMessage::STATUS_RESOLVED))
            ->orderByRaw('COALESCE(last_reply_at, created_at) DESC')
            ->limit(200)
            ->get()
            ->map(fn (ContactMessage $m) => $this->threadSummary($m));

        $selected = null;
        if ($request->filled('thread')) {
            $message = ContactMessage::query()
                ->with(['photographer:id,name', 'event:id,name', 'replies.author:id,name', 'replies.onBehalfOf:id,name'])
                ->find($request->integer('thread'));

            if ($message) {
                $selected = $this->threadDetail($message);
            }
        }

        return Inertia::render('Admin/Messages/Index', [
            'threads' => $threads,
            'selected' => $selected,
            'filter' => $filter,
            'counts' => [
                'all' => ContactMessage::query()->count(),
                'unanswered' => ContactMessage::query()->where('status', '!=', ContactMessage::STATUS_RESOLVED)->has('replies', '=', 0)->count(),
                'photographer' => ContactMessage::query()->whereNotNull('photographer_id')->count(),
            ],
        ]);
    }

    public function reply(Request $request, ContactMessage $message, ContactThread $thread): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'on_behalf_of_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('role', User::ROLE_PHOTOGRAPHER),
            ],
        ]);

        $onBehalfOf = null;
        if (! empty($data['on_behalf_of_id'])) {
            // Csak a szálhoz tartozó fotós nevében lehet válaszolni.
            abort_unless((string) $message->photographer_id === $data['on_behalf_of_id'], 422, 'Csak a szálhoz rendelt fotós nevében válaszolhatsz.');
            $onBehalfOf = User::find($data['on_behalf_of_id']);
        }

        $thread->reply($message, $data['body'], $request->user(), $onBehalfOf);

        return back()->with('success', $onBehalfOf
            ? "Válasz elküldve {$onBehalfOf->name} fotós nevében."
            : 'Válasz elküldve.');
    }

    public function updateStatus(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ContactMessage::STATUS_NEW, ContactMessage::STATUS_IN_PROGRESS, ContactMessage::STATUS_RESOLVED])],
        ]);

        $message->update(['status' => $data['status']]);

        return back()->with('success', 'Állapot frissítve.');
    }

    /**
     * @return array<string, mixed>
     */
    private function threadSummary(ContactMessage $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'subject' => $m->subject,
            'status' => $m->status,
            'photographer' => $m->photographer?->only(['id', 'name']),
            'replies_count' => $m->replies_count,
            'answered' => $m->replies_count > 0,
            'created_at' => $m->created_at?->toIso8601String(),
            'last_activity_at' => ($m->last_reply_at ?? $m->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function threadDetail(ContactMessage $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'subject' => $m->subject,
            'message' => $m->message,
            'status' => $m->status,
            'contact_type' => $m->contact_type,
            'photographer' => $m->photographer?->only(['id', 'name']),
            'event' => $m->event?->only(['id', 'name']),
            'created_at' => $m->created_at?->toIso8601String(),
            'replies' => $m->replies->map(fn (ContactReply $r) => [
                'id' => $r->id,
                'body' => $r->body,
                'author' => $r->author?->name,
                'on_behalf_of' => $r->onBehalfOf?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ];
    }
}
