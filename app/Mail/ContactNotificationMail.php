<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public ContactMessage $contactMessage) {}

    protected function templateKey(): string
    {
        return 'contact_notification';
    }

    protected function templateData(): array
    {
        return [
            'name' => $this->contactMessage->name,
            'email' => $this->contactMessage->email,
            'subject' => $this->contactMessage->subject,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->templateSubject(),
            replyTo: [$this->contactMessage->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-notification',
            with: $this->templateBlocks(),
        );
    }
}
