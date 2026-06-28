<?php

namespace App\Repositories;

use App\Models\Producer;
use Illuminate\Pagination\LengthAwarePaginator;

class ProducerRepository
{
    public function __construct(protected Producer $model)
    {
    }

    public function create(array $data): Producer
    {
        return $this->model->create($data);
    }

    public function findById(int $id): Producer
    {
        return $this->model->findOrFail($id);
    }

    public function findByUserId(int $userId): Producer
    {
        return $this->model
            ->whereHas('users', fn ($q) => $q->where('id', $userId))
            ->firstOrFail();
    }

    public function update(Producer $producer, array $data): Producer
    {
        $producer->update($data);

        return $producer->fresh();
    }

    /**
     * Pedidos dos eventos do produtor, paginados com filtros opcionais.
     */
    public function getOrdersForProducer(int $producerId, array $filters = []): LengthAwarePaginator
    {
        $query = \App\Models\Order::query()
            ->whereHas('event', fn ($q) => $q->where('producer_id', $producerId))
            ->with(['user', 'event', 'items.eventTicket', 'issuedTickets'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (! empty($filters['event_id'])) {
            $query->where('event_id', (int) $filters['event_id']);
        }

        if (! empty($filters['search'])) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('email', 'like', '%'.$filters['search'].'%'));
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Total de pedidos pagos em eventos futuros do produtor (aguardando liberação).
     */
    public function getPendingOrdersAmount(int $producerId): float
    {
        return round(
            (int) \App\Models\Order::query()
                ->whereHas('event', fn ($q) => $q->where('producer_id', $producerId)
                    ->where('date', '>', now()))
                ->where('payment_status', 'PAID')
                ->sum('producer_amount') / 100,
            2,
        );
    }
}
