<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Models\Place;
use App\Repositories\EventRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventService
{
    public function __construct(
        protected EventRepository $eventRepository,
        protected EventTicketSetupService $ticketSetupService,
        protected CloudinaryService $cloudinaryService,
        protected LocationService $locationService,
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
                    'name', 'description', 'location', 'location_name', 'address', 'latitude', 'longitude',
                    'place_id', 'date', 'category', 'status', 'ticket_type', 'has_seats', 'slug',
                ]);

                $slug = Str::slug($eventData['name']);
                $eventData['slug'] = $slug;

                $this->applyLocationData($eventData);

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
                    'name', 'description', 'location', 'location_name', 'address', 'latitude', 'longitude',
                    'place_id', 'date', 'category', 'status', 'ticket_type', 'has_seats', 'slug',
                ]);

                if (! empty($eventData)) {
                    $this->applyLocationData($eventData, $event->only(['address', 'latitude', 'longitude', 'place_id']));
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

    /**
     * @param  array<string, mixed>  $eventData
     * @param  array<string, mixed>|null  $existing
     */
    protected function applyLocationData(array &$eventData, ?array $existing = null): void
    {
        if (! empty($eventData['place_id'])) {
            $this->applyPlaceData($eventData);

            return;
        }

        if (array_key_exists('place_id', $eventData) && empty($eventData['place_id'])) {
            $eventData['place_id'] = null;
        }

        if (! empty($eventData['location_name'])) {
            $eventData['location'] = $eventData['location_name'];
        }

        $addressProvided = array_key_exists('address', $eventData);
        $address = trim((string) ($eventData['address'] ?? ''));

        if ($addressProvided && $address === '') {
            $eventData['latitude'] = null;
            $eventData['longitude'] = null;

            return;
        }

        if ($address === '') {
            return;
        }

        $addressChanged = $existing === null || $address !== trim((string) ($existing['address'] ?? ''));
        $hasManualCoordinates = array_key_exists('latitude', $eventData) || array_key_exists('longitude', $eventData);

        if (! $addressChanged && ! $hasManualCoordinates) {
            return;
        }

        if ($hasManualCoordinates
            && is_numeric($eventData['latitude'] ?? null)
            && is_numeric($eventData['longitude'] ?? null)) {
            $eventData['latitude'] = (float) $eventData['latitude'];
            $eventData['longitude'] = (float) $eventData['longitude'];

            return;
        }

        $coordinates = $this->locationService->geocode($address);

        if ($coordinates !== null) {
            $eventData['latitude'] = $coordinates['latitude'];
            $eventData['longitude'] = $coordinates['longitude'];

            return;
        }

        if ($addressChanged) {
            $eventData['latitude'] = null;
            $eventData['longitude'] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    protected function applyPlaceData(array &$eventData): void
    {
        $place = Place::query()->findOrFail((int) $eventData['place_id']);

        $eventData['location_name'] = $place->name;
        $eventData['location'] = $place->name;
        $eventData['address'] = $place->address;
        $eventData['latitude'] = $place->latitude;
        $eventData['longitude'] = $place->longitude;
    }
}
