<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if ($request->needsTwoFactorChallenge()) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended($this->redirectPathFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectPathFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => '/admin/dashboard',
            $user->isOrganizer() => '/organizer/dashboard',
            default => '/photographer/dashboard',
        };
    }
}
