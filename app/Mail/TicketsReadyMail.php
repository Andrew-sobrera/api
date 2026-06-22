<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\PdfTicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketsReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seus ingressos estão prontos — '.$this->order->event?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets-ready',
            with: [
                'order' => $this->order,
                'tickets' => $this->order->issuedTickets,
            ],
        );
    }
}
