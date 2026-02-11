<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyOeeReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reportData;
    public string $reportDate;
    public ?string $plantName;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, string $reportDate, ?string $plantName = null)
    {
        $this->reportData = $reportData;
        $this->reportDate = $reportDate;
        $this->plantName = $plantName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->plantName 
            ? "Daily OEE Report - {$this->plantName} ({$this->reportDate})"
            : "Daily OEE Report ({$this->reportDate})";
            
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-oee-report',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
