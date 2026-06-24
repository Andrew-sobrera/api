<?php

namespace App\Repositories;

use App\Models\Producer;

class ProducerRepository
{

    public function __construct(protected Producer $model)
    {
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }
}