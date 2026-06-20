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
        return $this->model->with('tickets')->get();
    }

    public function getById(int $id)
    {
        return $this->model->with('tickets')->findOrFail($id);
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