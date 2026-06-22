<?php

namespace App\Repositories;

use App\Enums\TicketStatus;
use App\Models\Ticket;

class TicketRepository
{
    public function __construct(protected Ticket $model)
    {
    }

    public function existsForOrder(int $orderId): bool
    {
        return $this->model->newQuery()->where('order_id', $orderId)->exists();
    }

    public function getForOrder(int $orderId)
    {
        return $this->model->newQuery()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();
    }

    public function countForOrderLine(int $orderId, int $eventTicketId, ?int $seatId = null): int
    {
        return $this->model->newQuery()
            ->where('order_id', $orderId)
            ->where('event_ticket_id', $eventTicketId)
            ->when($seatId, fn ($query) => $query->where('seat_id', $seatId))
            ->when(! $seatId, fn ($query) => $query->whereNull('seat_id'))
            ->count();
    }

    public function create(array $data): Ticket
    {
        return $this->model->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket->fresh();
    }

    public function findById(int $id): Ticket
    {
        return $this->model->newQuery()
            ->with(['event', 'eventTicket', 'sector', 'seat', 'order'])
            ->findOrFail($id);
    }

    public function findByHash(string $hash): ?Ticket
    {
        return $this->model->newQuery()->where('hash', $hash)->first();
    }

    public function getForUserEmail(string $email)
    {
        return $this->model->newQuery()
            ->with(['event', 'eventTicket', 'sector', 'seat'])
            ->where('buyer_email', $email)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getForEvent(int $eventId)
    {
        return $this->model->newQuery()
            ->with(['order', 'eventTicket', 'sector', 'seat'])
            ->where('event_id', $eventId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function markAsUsed(Ticket $ticket): Ticket
    {
        $ticket->update([
            'status' => TicketStatus::USED,
            'used_at' => now(),
        ]);

        return $ticket->fresh();
    }
}
