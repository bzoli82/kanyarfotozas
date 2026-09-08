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
use Illuminate\Support\Facades\URL;

/**
 * Egyszeri emlékeztető egy még ki nem fizetett (pending) rendelésről. A „folytatás"
 * gomb egy 7 napig érvényes, aláírt linkre mutat, ami a kosárba visszatölti a
 * rendelés még elérhető tételeit.
 */
class AbandonedCartMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public Order $order) {}

    protected function templateKey(): string
    {
        return 'abandoned_cart';
    }

    protected function templateData(): array
    {
        return ['item_count' => $this->order->media()->count()];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        $resumeUrl = URL::temporarySignedRoute('public.cart', now()->addDays(7), ['order' => $this->order->id]);

        return new Content(
            markdown: 'mail.abandoned-cart',
            with: [
                ...$this->templateBlocks(),
                'resumeUrl' => $resumeUrl,
                'itemCount' => $this->order->media()->count(),
            ],
        );
    }
}
