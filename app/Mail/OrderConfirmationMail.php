<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    public function __construct(public Order $order) {}

    protected function templateKey(): string
    {
        return 'order_confirmation';
    }

    protected function templateData(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number ?? (string) $this->order->id,
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
        $token = $this->order->download_token;
        $this->order->loadMissing('media.event:id,name');

        $invoice = $this->order->normalInvoice();

        return new Content(
            markdown: 'mail.order-confirmation',
            with: [
                ...$this->templateBlocks(),
                'downloadUrl' => route('public.download.show', $token),
                'zipUrl' => route('public.download.zip', $token),
                'invoiceUrl' => $invoice && filled($invoice->pdf_path) ? route('public.download.invoice', $token) : null,
                'items' => $this->order->media->map(fn (Media $media) => [
                    'title' => $media->event?->name ?? 'Média',
                    'type' => $media->type,
                    'price_cents' => $media->pivot->price_cents,
                    'formats' => collect($media->isVideo() ? ['mp4' => 'MP4'] : ['jpeg' => 'JPEG', 'webp' => 'WebP'])
                        ->map(fn ($label, $format) => [
                            'label' => $label,
                            'url' => route('public.download.file', [$token, $media->id, $format]),
                        ])->values(),
                ]),
                'expiresAt' => $this->order->token_expires_at,
            ],
        );
    }
}
