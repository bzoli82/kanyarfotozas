<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * A csapat (fotós / admin / superadmin) saját, nyilvános profilja — az, ami a
 * publikus „Fotósok" oldalon megjelenik: profilkép, bemutatkozó, elérhetőségek.
 *
 * A fotós ezt maga szerkesztheti (a bejelentkezési e-mailt, szerepkört, jutalékot
 * NEM — azokat csak a superadmin a fotós-részletnézetben).
 */
class TeamProfileController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Team/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar_s3_key,
                'bio' => $user->bio,
                'is_public' => (bool) $user->is_public,
                'public_email' => $user->public_email,
                'website' => $user->website,
                'social_facebook' => $user->social_facebook,
                'social_instagram' => $user->social_instagram,
                'social_youtube' => $user->social_youtube,
                'social_tiktok' => $user->social_tiktok,
            ],
            'publicUrl' => url('/photographers'),
            'contactsPublic' => (bool) SiteSetting::get('photographer_contacts_public', false),
        ]);
    }

    public function update(Request $request, ImageProcessingService $images): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['required', 'boolean'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
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

        return back()->with('success', 'Profil elmentve.');
    }

    /**
     * A Fotós Megállapodás egyszeri elfogadása a régi (meghívás előtti) fotósoknak.
     */
    public function acceptAgreement(Request $request): RedirectResponse
    {
        $request->validate(['accepted' => ['accepted']]);

        $user = $request->user();

        if ($user->agreed_terms_at === null) {
            $user->forceFill(['agreed_terms_at' => now()])->save();
        }

        return back()->with('success', 'Köszönjük — a Fotós Megállapodást elfogadottként rögzítettük.');
    }
}
