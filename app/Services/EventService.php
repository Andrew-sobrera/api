<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Repositories\EventRepository;
use App\Repositories\TicketEventRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class EventService
{
    protected $eventRepository;
    protected $ticketEventRepository;
    protected $cloudinaryService;

    public function __construct(
        EventRepository $eventRepository,
        TicketEventRepository $ticketEventRepository,
        CloudinaryService $cloudinaryService
    ) {
        $this->eventRepository = $eventRepository;
        $this->ticketEventRepository = $ticketEventRepository;
        $this->cloudinaryService = $cloudinaryService;
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
            $eventData = Arr::only($data, ['name', 'date', 'category', 'status', 'ticket_type', 'slug']);
            $event = $this->eventRepository->create($eventData);

            if ($data['ticket_type'] === 'simple') {
                $this->ticketEventRepository->create([
                    'event_id' => $event->id,
                    'price' => $data['ticket']['price'],
                    'quantity' => $data['ticket']['quantity'],
                ]);
            }

            return $event->load('tickets');
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }

    public function uploadBanner(int $id, UploadedFile $banner)
    {
        try {
            $event = $this->eventRepository->getById($id);

            if ($event->banner_public_id) {
                $this->cloudinaryService->delete($event->banner_public_id);
            }

            $upload = $this->cloudinaryService->uploadBanner($banner);

            $this->eventRepository->update($event, [
                'banner_url' => $upload['url'],
                'banner_public_id' => $upload['public_id'],
            ]);

            return $event->fresh('tickets');
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }
}
