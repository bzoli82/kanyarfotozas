<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Fiók-biztonság (/admin/settings/security) — minden admin a SAJÁT kétfaktoros
 * hitelesítését kezeli. A „kötelező mindenkinek" kapcsolót csak superadmin látja.
 */
class SecuritySettingsController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('2fa.pending_secret');

        return Inertia::render('Admin/Settings/Security', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => filled($user->two_factor_secret) && ! $user->hasTwoFactorEnabled(),
            'recoveryCodes' => $user->hasTwoFactorEnabled() || filled($user->two_factor_secret)
                ? $this->twoFactor->recoveryCodes($user)
                : [],
            'qr' => $pendingSecret ? $this->twoFactor->qrCodeSvg($user, $pendingSecret) : null,
            'setupKey' => $pendingSecret,
            'isSuperadmin' => $user->isSuperadmin(),
            'requiredForAdmins' => $this->twoFactor->isRequiredForAdmins(),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->hasTwoFactorEnabled(), 422);

        $secret = $this->twoFactor->generateSecret();
        $this->twoFactor->enable($user, $secret);
        $request->session()->put('2fa.pending_secret', $secret);

        return back()->with('success', 'Olvasd be a QR-kódot az authenticator appoddal, majd írd be a kódot.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = (string) $request->validate(['code' => ['required', 'string']])['code'];

        if (! $this->twoFactor->confirm($user, $code)) {
            throw ValidationException::withMessages(['code' => 'Érvénytelen kód — próbáld újra.']);
        }

        $request->session()->forget('2fa.pending_secret');

        return back()->with('success', 'A kétfaktoros hitelesítés bekapcsolva. Mentsd el a helyreállító kódokat!');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 422);

        $this->twoFactor->regenerateRecoveryCodes($request->user());

        return back()->with('success', 'Új helyreállító kódok — a régiek már nem érvényesek.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'Hibás jelszó.']);
        }

        if ($this->twoFactor->isRequiredForAdmins() && $user->isAdmin()) {
            throw ValidationException::withMessages([
                'password' => 'A kétfaktoros hitelesítés kötelező — nem kapcsolható ki.',
            ]);
        }

        $this->twoFactor->disable($user);
        $request->session()->forget('2fa.pending_secret');

        return back()->with('success', 'A kétfaktoros hitelesítés kikapcsolva.');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $required = (bool) $request->validate(['required' => ['required', 'boolean']])['required'];

        if ($required && ! $request->user()->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'required' => 'Előbb a saját fiókodon kapcsold be a 2FA-t.',
            ]);
        }

        $this->twoFactor->setRequiredForAdmins($required);

        return back()->with('success', $required
            ? 'Mostantól minden admin fióknak kötelező a kétfaktoros hitelesítés.'
            : 'A kétfaktoros hitelesítés már nem kötelező.');
    }
}
