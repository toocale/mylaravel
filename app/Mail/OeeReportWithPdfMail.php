<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class OeeReportWithPdfMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reportData;
    public string $pdfPath;
    public string $dateFrom;
    public string $dateTo;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, string $pdfPath, string $dateFrom, string $dateTo)
    {
        $this->reportData = $reportData;
        $this->pdfPath = $pdfPath;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "OEE Performance Report ({$this->dateFrom} to {$this->dateTo})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.oee-report-pdf',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('oee-report-' . $this->dateFrom . '-to-' . $this->dateTo . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
