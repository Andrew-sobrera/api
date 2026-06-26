<?php

namespace App\Repositories;

use App\Models\Event;

class EventRepository
{
    protected $model;

    public function __construct(Event $model)
    {
        $this->model = $model;
    }

    public function getAll()
    {
        return $this->model->with(['tickets.batches', 'sectors.tickets', 'sectors.seats'])->get();
    }

    public function getById(int $id)
    {
        return $this->model->with(['tickets.batches', 'sectors.tickets', 'sectors.seats'])->findOrFail($id);
    }

    public function getBySlug(string $slug)
    {
        return $this->model->with(['producer', 'tickets.batches', 'sectors.tickets', 'sectors.seats'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function getActivePublic()
    {
        return $this->model->with(['tickets.batches', 'sectors'])
            ->where('status', 'active')
            ->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(Event $event, array $data)
    {
        $event->update($data);

        return $event;
    }
}