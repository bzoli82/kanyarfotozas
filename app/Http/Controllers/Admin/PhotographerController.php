<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PhotographerInvitationMail;
use App\Mail\TemporaryPasswordMail;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\PhotographerPayoutService;
use App\Services\PhotographerVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Models\Activity;

class PhotographerController extends Controller
{
    /**
     * Fotos/admin lista + meg el nem fogadott meghivok — master.txt 9.3.
     */
    public function index(Request $request, PhotographerPayoutService $payouts): InertiaResponse
    {
        $search = $request->string('search')->value();
        $status = $request->string('status')->value(); // active|inactive
        $role = $request->string('role')->value(); // admin|photographer

        $payoutSummary = $payouts->summary()->keyBy('id');

        $teamRoles = [User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER, User::ROLE_ORGANIZER];

        $users = User::query()
            ->whereIn('role', $teamRoles)
            ->withCount('media')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'ILIKE', "%{$search}%")->orWhere('email', 'ILIKE', "%{$search}%")))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(in_array($role, $teamRoles, true), fn ($q) => $q->where('role', $role))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toDateString(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'media_count' => $user->media_count,
                'revenue_cents' => $this->revenueFor($user->id),
                'outstanding_cents' => (int) ($payoutSummary->get($user->id)['outstanding_cents'] ?? 0),
                'is_active' => $user->is_active,
                'is_public' => (bool) $user->is_public,
                'agreed_terms' => $user->role !== User::ROLE_PHOTOGRAPHER || $user->agreed_terms_at !== null,
            ]);

        $pendingInvitations = Invitation::query()
            ->whereNull('accepted_at')
            ->with('invitedBy:id,name')
            ->latest('created_at')
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
            ]);

        return Inertia::render('Admin/Photographers/Index', [
            'users' => $users,
            'pendingInvitations' => $pendingInvitations,
            'filters' => ['search' => $search, 'status' => $status, 'role' => $role],
            'payoutTotals' => [
                'outstanding_cents' => (int) $payoutSummary->sum('outstanding_cents'),
                'paid_cents' => (int) $payoutSummary->sum('total_paid_cents'),
            ],
            'contactsPublic' => app(PhotographerVisibility::class)->contactsPublic(),
            'attributionPublic' => app(PhotographerVisibility::class)->attributionPublic(),
        ]);
    }

    /**
     * A nyilvános fotós-láthatóság két kapcsolója (anti-disintermediation):
     *  - contacts_public: a „Fotósok" oldalon látszanak-e az elérhetőségek (alap: KI)
     *  - attribution_public: a kép-szintű „Fotós: X" + az esemény-kereső fotós-szűrője (alap: BE)
     */
    public function updateSettings(Request $request, PhotographerVisibility $visibility): RedirectResponse
    {
        $data = $request->validate([
            'contacts_public' => ['sometimes', 'boolean'],
            'attribution_public' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('contacts_public', $data)) {
            $visibility->setContactsPublic($data['contacts_public']);
        }

        if (array_key_exists('attribution_public', $data)) {
            $visibility->setAttributionPublic($data['attribution_public']);
        }

        return back()->with('success', 'Fotós-láthatóság elmentve.');
    }

    private function revenueFor(string $userId): int
    {
        return (int) DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', 'paid')
            ->where('media.photographer_id', $userId)
            ->sum('order_media.price_cents');
    }

    public function storeInvitation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('invitations', 'email')->where('accepted_at', null)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER, User::ROLE_ORGANIZER])],
            'revenue_share_percent' => ['nullable', 'required_unless:role,'.User::ROLE_ORGANIZER.','.User::ROLE_ADMIN, 'integer', 'min:0', 'max:100'],
        ]);

        $invitation = Invitation::create([
            ...$data,
            'revenue_share_percent' => $data['revenue_share_percent'] ?? 0,
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invitation->email)->send(new PhotographerInvitationMail($invitation));

        return back()->with('success', 'Meghívó elküldve.');
    }

    public function resendInvitation(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 422, 'A meghívó már elfogadva.');

        $invitation->reissue();
        Mail::to($invitation->email)->send(new PhotographerInvitationMail($invitation));

        return back()->with('success', 'Meghívó újraküldve.');
    }

    public function destroyInvitation(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 422, 'A meghívó már elfogadva.');

        $invitation->delete();

        return back()->with('success', 'Meghívó visszavonva.');
    }

    public function show(User $user, PhotographerPayoutService $payouts): InertiaResponse
    {
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER], true), 404);

        $mediaQuery = Media::query()->where('photographer_id', $user->id);

        $sales = DB::table('order_media')
            ->join('orders', 'orders.id', '=', 'order_media.order_id')
            ->join('media', 'media.id', '=', 'order_media.media_id')
            ->where('orders.payment_status', 'paid')
            ->where('media.photographer_id', $user->id)
            ->select(['orders.created_at as order_date', 'orders.buyer_email', 'media.id as media_id', 'media.thumbnail_s3_key', 'order_media.price_cents'])
            ->orderByDesc('orders.created_at')
            ->limit(50)
            ->get();

        $revenueCents = (int) $sales->sum('price_cents');
        $soldCount = $sales->count();

        return Inertia::render('Admin/Photographers/Show', [
            'photographer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'bio' => $user->bio,
                'avatar' => $user->avatar_s3_key,
                'is_public' => (bool) $user->is_public,
                'public_email' => $user->public_email,
                'website' => $user->website,
                'social_instagram' => $user->social_instagram,
                'social_facebook' => $user->social_facebook,
                'social_youtube' => $user->social_youtube,
                'social_tiktok' => $user->social_tiktok,
                'revenue_share_percent' => $user->revenue_share_percent,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at->toDateString(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'kpis' => [
                'uploaded_photos' => (clone $mediaQuery)->where('type', Media::TYPE_PHOTO)->count(),
                'uploaded_videos' => (clone $mediaQuery)->where('type', Media::TYPE_VIDEO)->count(),
                'sold_count' => $soldCount,
                'revenue_cents' => $revenueCents,
                'average_price_cents' => $soldCount > 0 ? (int) round($revenueCents / $soldCount) : 0,
            ],
            'revenueTrend' => app(DashboardStatsService::class)->revenueTrendForPhotographer($user->id),
            'events' => $user->createdEvents()->withCount('media')->latest('created_at')->limit(20)->get(['id', 'name', 'event_date', 'status'])
                ->map(fn ($event) => ['id' => $event->id, 'name' => $event->name, 'event_date' => $event->event_date?->toDateString(), 'media_count' => $event->media_count, 'status' => $event->status]),
            'sales' => $sales->map(fn ($row) => [
                'order_date' => $row->order_date,
                'media_id' => $row->media_id,
                'thumbnail_s3_key' => $row->thumbnail_s3_key,
                'price_cents' => $row->price_cents,
                'buyer_email_masked' => app(DashboardStatsService::class)->maskEmail($row->buyer_email),
            ]),
            'activity' => Activity::query()
                ->where('causer_id', $user->id)
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'subject_type' => class_basename($activity->subject_type),
                    'created_at' => $activity->created_at->toIso8601String(),
                    'properties' => $activity->properties,
                ]),
            'payout' => $user->role === User::ROLE_PHOTOGRAPHER ? $payouts->panelFor($user) : null,
        ]);
    }

    public function update(Request $request, User $user, ImageProcessingService $images): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PHOTOGRAPHER])],
            'revenue_share_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_youtube' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:8192'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);

        $publicDisk = Storage::disk(MediaStorage::public());

        if ($request->boolean('remove_avatar') || $request->hasFile('avatar')) {
            if ($user->avatar_s3_key) {
                $publicDisk->delete($user->avatar_s3_key);
            }
            $data['avatar_s3_key'] = null;
        }

        if ($request->hasFile('avatar')) {
            $key = 'avatars/'.Str::uuid().'.webp';
            $publicDisk->put($key, $images->makeAvatar($request->file('avatar')->getRealPath()));
            $data['avatar_s3_key'] = $key;
        }

        $user->update(collect($data)->except(['avatar', 'remove_avatar'])->all());

        return back()->with('success', 'Fotós adatai frissítve.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $temporaryPassword = Str::password(12);

        $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();

        Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));

        return back()->with('success', 'Ideiglenes jelszó elküldve e-mailben.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Media::query()->where('photographer_id', $user->id)->exists()) {
            return back()->with('error', 'A fotós nem törölhető, mert vannak feltöltött médiái — inaktiválja helyette.');
        }

        $user->delete();

        return redirect()->route('admin.photographers.index')->with('success', 'Fotós törölve.');
    }
}
