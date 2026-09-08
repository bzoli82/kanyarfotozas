<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\ContactMessage;
use App\Services\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public ContactMessage $contactMessage) {}

    protected function templateKey(): string
    {
        return 'contact_confirmation';
    }

    protected function templateData(): array
    {
        return [
            'name' => $this->contactMessage->name,
            'subject' => $this->contactMessage->subject,
        ];
    }

    public function envelope(): Envelope
    {
        $replyTo = app(MailSettings::class)->replyTo();

        return new Envelope(
            subject: $this->templateSubject(),
            replyTo: $replyTo ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-confirmation',
            with: $this->templateBlocks(),
        );
    }
}
