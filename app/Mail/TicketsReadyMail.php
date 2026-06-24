<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\PdfTicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
                'ticketCount' => $this->order->issuedTickets->count(),
            ],
        );
    }

    /**
     * Anexos HTML iguais ao fluxo "Baixar ingressos" / "Baixar ingresso" no app.
     * O cliente abre no navegador e usa Imprimir → Salvar como PDF.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        /** @var PdfTicketService $pdfTicketService */
        $pdfTicketService = app(PdfTicketService::class);

        $attachments = [];

        $orderHtml = $pdfTicketService->renderOrderTicketsHtml($this->order->id);
        $attachments[] = Attachment::fromData(
            fn () => $orderHtml,
            'pedido-'.$this->order->id.'-ingressos.html',
        )->withMime('text/html');

        foreach ($this->order->issuedTickets as $index => $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }

            $ticketHtml = $pdfTicketService->renderHtml($ticket);
            $label = Str::slug($ticket->eventTicket?->name ?? 'ingresso', '-');
            $filename = 'ingresso-'.($index + 1).'-'.$label.'.html';

            $attachments[] = Attachment::fromData(
                fn () => $ticketHtml,
                $filename,
            )->withMime('text/html');
        }

        return $attachments;
    }
}
