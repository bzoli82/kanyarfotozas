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

/**
 * Publikus „Csatlakozz fotósként" jelentkezés. Ugyanaz a védelem, mint a
 * kapcsolati űrlapon (FormGuard + hCaptcha + honeypot + rate limit). A
 * jelentkezés egy contact_messages sorként landol (contact_type =
 * photographer_application), így az admin „Üzenetek" felületén kezelhető.
 */
class PhotographerApplicationController extends Controller
{
    public function __construct(private FormGuard $guard, private CaptchaSettings $captcha) {}

    public function create(): Response
    {
        return Inertia::render('Info/PhotographerApplication', [
            'guard' => $this->guard->issue(),
            'hcaptcha' => $this->captcha->forView(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->guard->isBot($request)) {
            return back()->with('success', __('photographer_apply.sent'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'portfolio' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:255'],
            'shoots' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $hcaptcha = $this->captcha->enabled();

        if ($hcaptcha && ! $this->captcha->verify($request->input('h-captcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['hcaptcha' => __('formguard.hcaptcha')]);
        }

        $this->guard->verify($request, skipArithmetic: $hcaptcha);

        $body = collect([
            'Portfólió / közösségi oldal: '.($data['portfolio'] ?: '—'),
            'Régió / helyszínek: '.($data['region'] ?: '—'),
            'Miket fotóz: '.($data['shoots'] ?: '—'),
            '',
            $data['message'],
        ])->implode("\n");

        $contactMessage = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => 'Fotós-jelentkezés — '.$data['name'],
            'message' => $body,
            'contact_type' => ContactMessage::TYPE_PHOTOGRAPHER_APPLICATION,
            'status' => ContactMessage::STATUS_NEW,
        ]);

        Mail::to($contactMessage->email)->send(new ContactConfirmationMail($contactMessage));

        $recipients = User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email');
        if ($recipients->isNotEmpty()) {
            Mail::to($recipients->all())->send(new ContactNotificationMail($contactMessage));
        }

        return back()->with('success', __('photographer_apply.sent'));
    }
}
