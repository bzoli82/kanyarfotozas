<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\PurchaseOtpMail;
use App\Services\PurchaseLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Korábbi vásárlásaim" (/my-purchases) — EPIC-17. E-mail cim -> 6 jegyu OTP
 * (max 3/ora) -> a session-be kerul a verifikalt e-mail -> a hozza kotodo
 * fizetett rendelesek + letoltesi linkek. Regisztracio nincs.
 */
class MyPurchasesController extends Controller
{
    private const SESSION_KEY = 'verified_purchase_email';

    public function show(Request $request, PurchaseLookup $lookup): Response
    {
        $email = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('Public/MyPurchases', [
            'verifiedEmail' => $email,
            'orders' => $email ? $lookup->ordersFor($email) : [],
            'otpEmail' => $request->session()->get('otp_email'),
        ]);
    }

    public function requestOtp(Request $request, PurchaseLookup $lookup): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($lookup->isRateLimited($data['email'])) {
            return back()->withErrors(['email' => 'Túl sok kódkérés. Próbáld újra egy óra múlva.']);
        }

        $otp = $lookup->issueOtp($data['email']);
        Mail::to($data['email'])->send(new PurchaseOtpMail($otp));

        $request->session()->put('otp_email', $data['email']);

        return back()->with('success', 'Elküldtük a belépési kódot az e-mail címedre.');
    }

    public function verify(Request $request, PurchaseLookup $lookup): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $lookup->verify($data['email'], $data['otp'])) {
            return back()->withErrors(['otp' => 'Érvénytelen vagy lejárt kód.']);
        }

        $request->session()->put(self::SESSION_KEY, $data['email']);
        $request->session()->forget('otp_email');

        return redirect()->route('public.my-purchases');
    }

    public function resend(Request $request, PurchaseLookup $lookup): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_KEY);
        abort_unless($email, 403);

        $orderId = (int) $request->validate(['order_id' => ['required', 'integer']])['order_id'];

        $order = $lookup->resendDownloadLink($email, $orderId);

        if (! $order) {
            return back()->withErrors(['order_id' => 'A rendelés nem található.']);
        }

        return back()->with('success', 'A letöltési link frissítve — nyisd meg a listából.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('public.my-purchases');
    }
}
