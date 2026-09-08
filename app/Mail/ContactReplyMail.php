<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\ContactReply;
use App\Services\MailSettings;
use App\Services\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public ContactReply $reply) {}

    protected function templateKey(): string
    {
        return 'contact_reply';
    }

    protected function templateData(): array
    {
        $message = $this->reply->contactMessage;

        return [
            'name' => $message->name,
            'subject' => $message->subject,
            'replier' => $this->replierName(),
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
            markdown: 'mail.contact-reply',
            with: [
                ...$this->templateBlocks(),
                'replyBody' => $this->reply->body,
                'originalMessage' => $this->reply->contactMessage->message,
            ],
        );
    }

    /**
     * A valaszolo neve: ha az admin egy fotos NEVEBEN valaszolt, a fotos neve;
     * kulonben az oldal neve (a csapat).
     */
    private function replierName(): string
    {
        if ($this->reply->on_behalf_of_id) {
            return $this->reply->onBehalfOf?->name
                ?? app(SiteBranding::class)->name();
        }

        return app(SiteBranding::class)->name();
    }
}
