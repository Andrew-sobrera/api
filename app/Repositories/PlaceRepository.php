<?php

namespace App\Repositories;

use App\Models\Place;

class PlaceRepository
{
    public function __construct(protected Place $model)
    {
    }

    public function getAll()
    {
        return $this->model->orderBy('name')->get();
    }

    public function getById(int $id): Place
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Place
    {
        return $this->model->create($data);
    }

    public function update(Place $place, array $data): Place
    {
        $place->update($data);

        return $place->fresh();
    }

    public function delete(Place $place): void
    {
        $place->delete();
    }
}
