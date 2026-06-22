<?php

namespace App\Repositories;

use App\Exceptions\InsufficientStockException;
use App\Models\TicketEvent;

class EventTicketRepository extends TicketEventRepository
{
    public function findForUpdate(int $id): TicketEvent
    {
        return $this->model->newQuery()->lockForUpdate()->findOrFail($id);
    }

    public function findById(int $id): TicketEvent
    {
        return $this->model->findOrFail($id);
    }

    public function reserveTickets(int $ticketId, int $quantity): TicketEvent
    {
        $ticket = $this->findForUpdate($ticketId);

        if ($ticket->quantity < $quantity) {
            throw new InsufficientStockException();
        }

        return $this->decrementQuantity($ticket, $quantity);
    }

    public function releaseTickets(int $ticketId, int $quantity): TicketEvent
    {
        $ticket = $this->findById($ticketId);

        return $this->incrementQuantity($ticket, $quantity);
    }

    public function decrementQuantity(TicketEvent $ticket, int $quantity): TicketEvent
    {
        $ticket->decrement('quantity', $quantity);

        return $ticket->fresh();
    }

    public function incrementQuantity(TicketEvent $ticket, int $quantity): TicketEvent
    {
        $ticket->increment('quantity', $quantity);

        return $ticket->fresh();
    }
}
