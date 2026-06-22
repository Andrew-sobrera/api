<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Repositories\TicketRepository;
use App\Services\PdfTicketService;
use Illuminate\Http\Request;

class PublicTicketController extends Controller
{
    public function __construct(
        protected TicketRepository $ticketRepository,
        protected PdfTicketService $pdfTicketService,
    ) {
    }

    public function show(string $hash)
    {
        $ticket = $this->ticketRepository->findByHash($hash);

        if (! $ticket) {
            abort(404);
        }

        return new TicketResource($ticket->load(['event', 'eventTicket', 'sector', 'seat']));
    }

    public function pdf(string $hash)
    {
        $ticket = $this->ticketRepository->findByHash($hash);

        if (! $ticket) {
            abort(404);
        }

        $html = $this->pdfTicketService->renderHtml($ticket);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="ingresso-'.$hash.'.html"',
        ]);
    }
}
