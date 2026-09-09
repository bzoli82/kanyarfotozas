<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactNotificationMail;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\User;
use App\Services\CaptchaSettings;
use App\Support\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * A média-oldali „Kérdés a fotóshoz" űrlap — a feltöltő fotósnak címzett üzenetet
 * hoz létre (contact_type=photographer, photographer_id kitöltve). Ugyanaz a
 * védelem, mint a sima kapcsolati űrlapon: App\Support\FormGuard (time-trap +
 * számtani kérdés / hCaptcha + proof-of-work + honeypot) + rétegzett rate limit.
 */
class PhotographerQuestionController extends Controller
{
    public function __construct(private FormGuard $guard, private CaptchaSettings $captcha) {}

    public function store(Request $request, Media $media): RedirectResponse
    {
        abort_unless($media->isReady() && $media->photographer_id, 404);

        if ($this->guard->isBot($request)) {
            return back()->with('success', 'Köszönjük a kérdésed!');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $hcaptcha = $this->captcha->enabled();

        if ($hcaptcha && ! $this->captcha->verify($request->input('h-captcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['hcaptcha' => __('formguard.hcaptcha')]);
        }

        $this->guard->verify($request, skipArithmetic: $hcaptcha);

        $media->loadMissing('event');

        $contactMessage = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => 'Kérdés a fotóhoz — '.($media->event?->name ?? 'fotózás'),
            'message' => $data['message'],
            'contact_type' => ContactMessage::TYPE_PHOTOGRAPHER,
            'photographer_id' => $media->photographer_id,
            'event_id' => $media->event_id,
            'status' => ContactMessage::STATUS_NEW,
        ]);

        Mail::to($contactMessage->email)->send(new ContactConfirmationMail($contactMessage));

        $recipients = User::query()
            ->where(fn ($q) => $q->where('role', User::ROLE_SUPERADMIN)->orWhereKey($media->photographer_id))
            ->pluck('email')
            ->unique();

        if ($recipients->isNotEmpty()) {
            Mail::to($recipients->all())->send(new ContactNotificationMail($contactMessage));
        }

        return back()->with('success', 'Elküldtük a kérdésed a fotósnak. A választ e-mailben kapod meg.');
    }
}
