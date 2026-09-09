<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhotographerVisibility;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nyilvános „Fotósok" oldal — a csapat (nyilvános profilú, aktív fotósok és
 * adminok). A profilképet + bemutatkozót az admin a fotós-részletnézetben tölti fel.
 *
 * A fotósok elérhetőségei (céges e-mail, weboldal, közösségi linkek) CSAK akkor
 * jelennek meg, ha a superadmin engedélyezte (`photographer_contacts_public`,
 * alap: KI) — így a vásárló nem tudja megkerülni az oldalt a fotós közvetlen
 * megkeresésével.
 */
class PhotographerController extends Controller
{
    public function index(PhotographerVisibility $visibility): Response
    {
        $contactsPublic = $visibility->contactsPublic();

        $team = User::query()
            ->whereIn('role', [User::ROLE_PHOTOGRAPHER, User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderByRaw("array_position(ARRAY['photographer','admin','superadmin']::text[], role::text)")
            ->orderBy('name')
            ->get(['name', 'bio', 'avatar_s3_key', 'role', 'public_email', 'website', 'social_instagram', 'social_facebook', 'social_youtube', 'social_tiktok'])
            ->map(fn (User $user) => [
                'name' => $user->name,
                'bio' => $user->bio,
                'avatar' => $user->avatar_s3_key,
                'is_photographer' => $user->role === User::ROLE_PHOTOGRAPHER,
                'contacts' => $contactsPublic ? $user->publicContacts() : [],
            ])
            ->values();

        return Inertia::render('Info/Photographers', [
            'photographers' => $team,
            'contactsPublic' => $contactsPublic,
        ]);
    }
}
