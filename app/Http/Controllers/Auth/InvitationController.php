<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvitationController extends Controller
{
    public function show(string $token): InertiaResponse
    {
        $invitation = Invitation::query()->where('token', $token)->firstOrFail();

        abort_if($invitation->isAccepted(), 404);

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'isExpired' => $invitation->isExpired(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::query()->where('token', $token)->firstOrFail();

        abort_if($invitation->isAccepted(), 404);

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => 'A meghívó lejárt — kérj újat az adminisztrátortól.']);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $invitation->name,
            'email' => $invitation->email,
            'password' => Hash::make($data['password']),
            'role' => $invitation->role,
            'revenue_share_percent' => $invitation->revenue_share_percent,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->isAdmin() ? '/admin/dashboard' : '/photographer/dashboard');
    }
}
