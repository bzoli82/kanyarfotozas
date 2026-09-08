<?php

namespace App\Mail;

use App\Services\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProactiveAlertDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{key: string, severity: string, title: string, description: string, action_url: ?string, action_label: ?string}>  $alerts
     */
    public function __construct(public array $alerts) {}

    public function envelope(): Envelope
    {
        $count = count($this->alerts);

        return new Envelope(
            subject: app(SiteBranding::class)->name()." — {$count} sürgős rendszerfigyelmeztetés",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.proactive-alert-digest',
            with: [
                'alerts' => $this->alerts,
                'dashboardUrl' => route('admin.dashboard'),
            ],
        );
    }
}
