<?php

namespace App\Repositories;

use App\Models\TicketEvent;

class TicketEventRepository
{
    protected $model;

    public function __construct(TicketEvent $model)
    {
        $this->model = $model;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

}   