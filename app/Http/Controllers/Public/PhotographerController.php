<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nyilvános „Fotósok" oldal — a csapat (nyilvános profilú, aktív fotósok és
 * adminok). A profilképet + bemutatkozót az admin a fotós-részletnézetben tölti fel.
 */
class PhotographerController extends Controller
{
    public function index(): Response
    {
        $team = User::query()
            ->whereIn('role', [User::ROLE_PHOTOGRAPHER, User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderByRaw("array_position(ARRAY['photographer','admin','superadmin']::text[], role::text)")
            ->orderBy('name')
            ->get(['name', 'bio', 'avatar_s3_key', 'role', 'social_instagram', 'social_facebook', 'social_youtube'])
            ->map(fn (User $user) => [
                'name' => $user->name,
                'bio' => $user->bio,
                'avatar' => $user->avatar_s3_key,
                'is_photographer' => $user->role === User::ROLE_PHOTOGRAPHER,
                'socials' => array_filter([
                    'instagram' => $user->social_instagram,
                    'facebook' => $user->social_facebook,
                    'youtube' => $user->social_youtube,
                ]),
            ])
            ->values();

        return Inertia::render('Info/Photographers', [
            'photographers' => $team,
        ]);
    }
}
