<?php

namespace App\Repositories;

use App\Models\CartItem;
use Illuminate\Support\Collection;

class CartRepository
{
    public function __construct(protected CartItem $model)
    {
    }

    public function getForUser(int $userId, ?int $eventId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['eventTicket', 'sector', 'batch', 'seat', 'event'])
            ->where('user_id', $userId);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        return $query->get();
    }

    public function findForUser(int $userId, int $itemId): CartItem
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->findOrFail($itemId);
    }

    public function findLine(int $userId, int $eventTicketId, ?int $batchId, ?int $seatId): ?CartItem
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('event_ticket_id', $eventTicketId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when(! $batchId, fn ($q) => $q->whereNull('batch_id'))
            ->when($seatId, fn ($q) => $q->where('seat_id', $seatId))
            ->when(! $seatId, fn ($q) => $q->whereNull('seat_id'))
            ->first();
    }

    public function create(array $data): CartItem
    {
        return $this->model->create($data);
    }

    public function update(CartItem $item, array $data): CartItem
    {
        $item->update($data);

        return $item->fresh(['eventTicket', 'sector', 'batch', 'seat', 'event']);
    }

    public function delete(CartItem $item): void
    {
        $item->delete();
    }

    public function clearForUser(int $userId, ?int $eventId = null): void
    {
        $query = $this->model->newQuery()->where('user_id', $userId);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $query->delete();
    }
}
