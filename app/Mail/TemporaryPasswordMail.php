<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemporaryPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public User $user, public string $temporaryPassword) {}

    protected function templateKey(): string
    {
        return 'temporary_password';
    }

    protected function templateData(): array
    {
        return [
            'name' => $this->user->name,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.temporary-password',
            with: [
                ...$this->templateBlocks(),
                'loginUrl' => route('login'),
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
