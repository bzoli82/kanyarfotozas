<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotographerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public Invitation $invitation) {}

    protected function templateKey(): string
    {
        return 'photographer_invitation';
    }

    protected function templateData(): array
    {
        return [
            'role' => $this->invitation->role === 'admin' ? 'adminisztrátor' : 'fotós',
            'invited_by' => $this->invitation->invitedBy?->name ?? 'Az admin',
            'expires_at' => $this->invitation->expires_at?->translatedFormat('Y. m. d. H:i'),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.photographer-invitation',
            with: [
                ...$this->templateBlocks(),
                'acceptUrl' => route('invitations.show', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
