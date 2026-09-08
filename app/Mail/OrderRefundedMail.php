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

class OrderRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public Order $order, public int $amountCents) {}

    protected function templateKey(): string
    {
        return 'order_refunded';
    }

    protected function templateData(): array
    {
        return [
            'order_number' => $this->order->order_number ?? (string) $this->order->id,
            'amount' => number_format($this->amountCents, 0, ',', ' ').' Ft',
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-refunded',
            with: [
                ...$this->templateBlocks(),
                'fullRefund' => $this->order->refunded_cents >= $this->order->total_cents,
                'amount' => number_format($this->amountCents, 0, ',', ' ').' Ft',
            ],
        );
    }
}
