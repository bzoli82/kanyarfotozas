<?php

namespace App\Mail;

use App\Models\ErrorEvent;
use App\Services\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ErrorEvent $event, public bool $isNew) {}

    public function envelope(): Envelope
    {
        $prefix = $this->isNew ? 'Új hiba' : 'Ismétlődő hiba';

        return new Envelope(
            subject: app(SiteBranding::class)->name()." — {$prefix}: {$this->event->exception_class}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.error-notification',
            with: [
                'event' => $this->event,
                'isNew' => $this->isNew,
                'url' => rescue(fn () => route('admin.errors.index'), null, false),
            ],
        );
    }
}
