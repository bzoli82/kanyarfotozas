<?php

namespace App\Listeners;

use App\Models\SentEmail;
use Illuminate\Mail\Events\MessageSent;

/**
 * Minden sikeresen elküldött e-mailt naplóz (címzett + tárgy + a Mailable
 * osztálya, ha kiderül) — az admin „E-mail napló" nézethez. Sose dob (a
 * levélküldést nem akaszthatja meg).
 */
class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $to = $event->message->getTo();
            $recipient = ! empty($to) ? $to[0]->getAddress() : '—';

            $mailable = null;
            foreach (($event->data ?? []) as $value) {
                if (is_object($value) && str_contains($value::class, '\\Mail\\')) {
                    $mailable = class_basename($value);
                    break;
                }
            }

            SentEmail::create([
                'recipient' => mb_substr($recipient, 0, 255),
                'subject' => mb_substr((string) $event->message->getSubject(), 0, 255),
                'mailable' => $mailable,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A naplózás hibája sose törje meg a levélküldést.
        }
    }
}
