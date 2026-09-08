<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyPhotographerReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesMailTemplate;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public User $photographer, public array $report, public string $csv) {}

    protected function templateKey(): string
    {
        return 'photographer_report';
    }

    protected function templateData(): array
    {
        return [
            'name' => $this->photographer->name,
            'period' => 'Havi',
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
                'period' => 'havi',
                'photographer' => $this->photographer,
                'report' => $this->report,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, 'ertekesitesek-'.$this->report['from']->format('Y-m').'.csv')
                ->withMime('text/csv'),
        ];
    }
}
