<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $message;
    public $recipient;

    /**
     * Create a new message instance.
     */
    public function __construct(Ticket $ticket, TicketMessage $message, User $recipient)
    {
        $this->ticket = $ticket;
        $this->message = $message;
        $this->recipient = $recipient;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Message on Ticket #{$this->ticket->id}: {$this->ticket->subject}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-message',
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
