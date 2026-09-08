<?php

namespace App\Services;

use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Egy kapcsolati üzenet-szálhoz válasz rögzítése + kiküldése a feladónak.
 * Közös logika az admin és a fotós felület mögött.
 */
class ContactThread
{
    /**
     * @param  User  $author  a válaszoló (admin vagy fotós)
     * @param  User|null  $onBehalfOf  ha az admin egy fotós nevében válaszol
     */
    public function reply(ContactMessage $message, string $body, User $author, ?User $onBehalfOf = null): ContactReply
    {
        $reply = $message->replies()->create([
            'author_id' => $author->id,
            'on_behalf_of_id' => $onBehalfOf?->id,
            'body' => $body,
            'emailed' => true,
        ]);

        $message->forceFill([
            'last_reply_at' => $reply->created_at,
            'status' => ContactMessage::STATUS_IN_PROGRESS,
        ])->save();

        Mail::to($message->email)->send(new ContactReplyMail($reply->fresh(['contactMessage', 'onBehalfOf'])));

        activity()
            ->performedOn($message)
            ->causedBy($author)
            ->log($onBehalfOf
                ? "Válasz a(z) #{$message->id} üzenetre ({$onBehalfOf->name} fotós nevében)"
                : "Válasz a(z) #{$message->id} üzenetre");

        return $reply;
    }
}
