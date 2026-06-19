<?php

namespace App\Services;

use App\Repositories\EventRepository;
use App\Repositories\TicketEventRepository;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BaseException;


class EventService
{
    protected $eventRepository;
    protected $ticketEventRepository;

    public function __construct(EventRepository $eventRepository, TicketEventRepository $ticketEventRepository)
    {
        $this->eventRepository = $eventRepository;
        $this->ticketEventRepository = $ticketEventRepository;
    }

    public function getAll()
    {
        return $this->eventRepository->getAll();
    }

    public function getById(int $id)
    {
        return $this->eventRepository->getById($id);
    }

    public function create(array $data)
    {
        try {
            $event = $this->eventRepository->create($data);

            if ($data['ticket_type'] === 'simple') {
                $this->ticketEventRepository->create([
                    'event_id' => $event->id,
                    'price' => $data['ticket']['price'],
                    'quantity' => $data['ticket']['quantity'],
                ]);
            }
            return $event;
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }
}       