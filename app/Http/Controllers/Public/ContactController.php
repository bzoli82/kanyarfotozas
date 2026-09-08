<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactNotificationMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\CaptchaSettings;
use App\Support\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(private FormGuard $guard, private CaptchaSettings $captcha) {}

    public function create(): Response
    {
        return Inertia::render('Info/Contact', [
            'guard' => $this->guard->issue(),
            'hcaptcha' => $this->captcha->forView(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a rejtett mezőt ember nem tölti ki, csak bot. Ha kitöltötte,
        // úgy csinálunk, mintha sikerült volna (a botnak ne adjunk visszajelzést).
        if ($this->guard->isBot($request)) {
            return back()->with('success', 'Köszönjük az üzeneted!');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $hcaptcha = $this->captcha->enabled();

        if ($hcaptcha && ! $this->captcha->verify($request->input('h-captcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['hcaptcha' => 'Erősítsd meg, hogy nem vagy robot.']);
        }

        $this->guard->verify($request, skipArithmetic: $hcaptcha);

        $contactMessage = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => ContactMessage::STATUS_NEW,
        ]);

        Mail::to($contactMessage->email)->send(new ContactConfirmationMail($contactMessage));

        $recipients = User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email');
        if ($recipients->isNotEmpty()) {
            Mail::to($recipients->all())->send(new ContactNotificationMail($contactMessage));
        }

        return back()->with('success', 'Köszönjük az üzeneted! Hamarosan válaszolunk, a visszaigazolást e-mailben is elküldtük.');
    }
}
