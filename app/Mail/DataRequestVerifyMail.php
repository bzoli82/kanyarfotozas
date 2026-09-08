<?php

namespace App\Mail;

use App\Models\DataRequest;
use App\Services\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataRequestVerifyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DataRequest $dataRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(SiteBranding::class)->name().' — '.($this->dataRequest->type === DataRequest::TYPE_DELETE
                ? 'adattörlési kérelem megerősítése'
                : 'adatkiadási kérelem megerősítése'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.data-request-verify',
            with: [
                'isDelete' => $this->dataRequest->type === DataRequest::TYPE_DELETE,
                'verifyUrl' => route('public.data-request.verify', $this->dataRequest->token),
            ],
        );
    }
}
