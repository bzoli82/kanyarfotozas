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

class WeeklyPhotographerReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public User $photographer, public array $report) {}

    protected function templateKey(): string
    {
        return 'photographer_report';
    }

    protected function templateData(): array
    {
        return [
            'name' => $this->photographer->name,
            'period' => 'Heti',
            'from' => $this->report['from']->translatedFormat('Y. m. d.'),
            'to' => $this->report['to']->translatedFormat('Y. m. d.'),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->templateSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.photographer-report',
            with: [
                ...$this->templateBlocks(),
                'period' => 'heti',
                'photographer' => $this->photographer,
                'report' => $this->report,
            ],
        );
    }
}
