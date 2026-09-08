<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventLiveNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public EventSubscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Az „{$this->event->name}” felvételei most elérhetők!",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-live-notification',
            with: [
                'event' => $this->event,
                'galleryUrl' => route('public.events.show', $this->event->slug),
                'unsubscribeUrl' => route('public.unsubscribe', $this->subscription->unsubscribe_token),
            ],
        );
    }
}
