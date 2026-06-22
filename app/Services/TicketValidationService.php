<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Repositories\TicketRepository;

class TicketValidationService
{
    public function __construct(
        protected TicketRepository $ticketRepository,
        protected TicketHashService $hashService,
    ) {
    }

    public function validate(string $qrPayload): array
    {
        $parsed = $this->hashService->parse($qrPayload);

        if (! $parsed) {
            return $this->fail('QR Code inválido.');
        }

        $ticket = $this->ticketRepository->findById($parsed['ticket_id']);

        if (! $this->hashService->validate($ticket, $qrPayload)) {
            return $this->fail('QR Code não autenticado.');
        }

        if ($ticket->status === TicketStatus::USED) {
            return $this->fail('Ingresso já utilizado.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return $this->fail('Ingresso cancelado.');
        }

        $this->ticketRepository->markAsUsed($ticket);

        return [
            'valid' => true,
            'ticket' => $ticket->fresh(['event', 'eventTicket', 'sector', 'seat']),
        ];
    }

    private function fail(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
        ];
    }
}
