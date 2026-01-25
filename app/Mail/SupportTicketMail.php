<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketMail extends Mailable
{
    // ... (Use traits)
    public $ticketData;

    public function __construct($ticketData)
    {
        $this->ticketData = $ticketData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TIKET DUKUNGAN BARU: ' . $this->ticketData['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support_ticket',
            with: [
                'name' => $this->ticketData['sender_name'],
                'email' => $this->ticketData['sender_email'],
                'subject' => $this->ticketData['subject'],
                'message' => $this->ticketData['message'],
            ],
        );
    }
}
