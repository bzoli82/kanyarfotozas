<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DownloadReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public Order $order) {}

    protected function templateKey(): string
    {
        return 'download_reminder';
    }

    protected function templateData(): array
    {
        return [
            'expires_at' => $this->order->token_expires_at?->translatedFormat('Y. m. d. H:i'),
            'item_count' => $this->order->media()->count(),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.download-reminder',
            with: [
                ...$this->templateBlocks(),
                'downloadUrl' => route('public.download.show', $this->order->download_token),
                'expiresAt' => $this->order->token_expires_at,
                'itemCount' => $this->order->media()->count(),
            ],
        );
    }
}
