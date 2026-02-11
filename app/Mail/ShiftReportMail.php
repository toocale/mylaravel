<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShiftReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reportData;
    public string $machineName;
    public string $reportDate;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, string $machineName, string $reportDate)
    {
        $this->reportData = $reportData;
        $this->machineName = $machineName;
        $this->reportDate = $reportDate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Shift Report - {$this->machineName} ({$this->reportDate})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.shift-report',
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
