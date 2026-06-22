<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Repositories\EventRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        protected EventRepository $eventRepository,
        protected EventTicketSetupService $ticketSetupService,
        protected CloudinaryService $cloudinaryService
    ) {
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
            return DB::transaction(function () use ($data) {
                $eventData = Arr::only($data, [
                    'name', 'description', 'location', 'date', 'category', 'status', 'ticket_type', 'has_seats', 'slug',
                ]);
                $event = $this->eventRepository->create($eventData);

                $this->ticketSetupService->setup($event, $data);

                return $this->eventRepository->getById($event->id);
            });
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }

    public function update(int $id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $event = $this->eventRepository->getById($id);

                $eventData = Arr::only($data, [
                    'name', 'description', 'location', 'date', 'category', 'status', 'ticket_type', 'has_seats', 'slug',
                ]);

                if (! empty($eventData)) {
                    $this->eventRepository->update($event, $eventData);
                }

                if (isset($data['ticket_type'])) {
                    $this->ticketSetupService->sync($event->fresh(), $data);
                }

                return $this->eventRepository->getById($id);
            });
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

            return $this->eventRepository->getById($id);
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }
}
