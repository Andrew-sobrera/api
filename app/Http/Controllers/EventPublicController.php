<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Repositories\EventRepository;
use App\Services\TicketBatchService;

class EventPublicController extends Controller
{
    public function __construct(
        protected EventRepository $eventRepository,
        protected TicketBatchService $batchService,
    ) {
    }

    public function index()
    {
        return EventResource::collection($this->eventRepository->getActivePublic());
    }

    public function getBySlug(string $slug)
    {
        $event = $this->eventRepository->getBySlug($slug);

        return new EventResource($event);
    }
}
