<?php

namespace App\Mail;

use App\Services\PurchaseLookup;
use App\Services\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(SiteBranding::class)->name()." — belépési kód: {$this->otp}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.purchase-otp',
            with: [
                'otp' => $this->otp,
                'ttlMinutes' => PurchaseLookup::OTP_TTL_MINUTES,
            ],
        );
    }
}
