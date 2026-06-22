<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\View;

class PdfTicketService
{
    public function renderHtml(Ticket $ticket): string
    {
        $ticket->load(['event', 'eventTicket', 'sector', 'seat']);

        return View::make('tickets.pdf', ['ticket' => $ticket])->render();
    }

    public function renderOrderTicketsHtml(int $orderId): string
    {
        $tickets = Ticket::query()
            ->with(['event', 'eventTicket', 'sector', 'seat'])
            ->where('order_id', $orderId)
            ->get();

        return View::make('tickets.order-pdf', ['tickets' => $tickets])->render();
    }
}
