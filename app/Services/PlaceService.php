<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Jobs\GeocodePlaceJob;
use App\Models\Place;
use App\Models\PlaceAudit;
use App\Repositories\PlaceRepository;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlaceService
{
    public function __construct(
        protected PlaceRepository $placeRepository,
        protected GeocodingService $geocodingService,
    ) {
    }

    public function list()
    {
        return $this->placeRepository->getAll();
    }

    public function getById(int $id): Place
    {
        return $this->placeRepository->getById($id);
    }

    public function create(array $data): Place
    {
        try {
            return DB::transaction(function () use ($data) {
                $address = trim((string) ($data['address'] ?? ''));
                $normalized = $this->geocodingService->normalizeAddress($address);

                $placeData = [
                    'name' => $data['name'],
                    'address' => $address,
                    'address_normalized' => $normalized,
                    'geocoding_status' => Place::STATUS_PENDING,
                ];

                $hasManualCoordinates = isset($data['latitude'], $data['longitude'])
                    && is_numeric($data['latitude'])
                    && is_numeric($data['longitude']);

                if ($hasManualCoordinates) {
                    $placeData['latitude'] = (float) $data['latitude'];
                    $placeData['longitude'] = (float) $data['longitude'];
                    $placeData['geocoding_status'] = Place::STATUS_COMPLETED;
                    $placeData['provider'] = 'manual';
                } else {
                    $cached = $this->geocodingService->resolveFromCache($address);

                    if ($cached !== null) {
                        $placeData['latitude'] = $cached->latitude;
                        $placeData['longitude'] = $cached->longitude;
                        $placeData['provider'] = $cached->provider;
                        $placeData['geocoding_status'] = Place::STATUS_COMPLETED;
                    }
                }

                $place = $this->placeRepository->create($placeData);

                if ($place->geocoding_status === Place::STATUS_PENDING) {
                    GeocodePlaceJob::dispatch($place->id);
                }

                return $place;
            });
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }

    public function update(int $id, array $data): Place
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $place = $this->placeRepository->getById($id);
                $oldLat = $place->latitude;
                $oldLng = $place->longitude;

                $updateData = [];

                if (array_key_exists('name', $data)) {
                    $updateData['name'] = $data['name'];
                }

                $addressChanged = false;
                $coordinatesChanged = false;

                if (array_key_exists('address', $data)) {
                    $address = trim((string) $data['address']);
                    $updateData['address'] = $address;
                    $updateData['address_normalized'] = $this->geocodingService->normalizeAddress($address);
                    $addressChanged = $address !== $place->address;
                }

                $hasManualCoordinates = array_key_exists('latitude', $data) && array_key_exists('longitude', $data)
                    && is_numeric($data['latitude'])
                    && is_numeric($data['longitude']);

                if ($hasManualCoordinates) {
                    $newLat = (float) $data['latitude'];
                    $newLng = (float) $data['longitude'];
                    $coordinatesChanged = $newLat !== $oldLat || $newLng !== $oldLng;

                    $updateData['latitude'] = $newLat;
                    $updateData['longitude'] = $newLng;
                    $updateData['provider'] = 'manual';
                    $updateData['geocoding_status'] = Place::STATUS_COMPLETED;
                } elseif ($addressChanged) {
                    $cached = $this->geocodingService->resolveFromCache($updateData['address']);

                    if ($cached !== null) {
                        $updateData['latitude'] = $cached->latitude;
                        $updateData['longitude'] = $cached->longitude;
                        $updateData['provider'] = $cached->provider;
                        $updateData['geocoding_status'] = Place::STATUS_COMPLETED;
                        $coordinatesChanged = $cached->latitude !== $oldLat || $cached->longitude !== $oldLng;
                    } else {
                        $updateData['latitude'] = null;
                        $updateData['longitude'] = null;
                        $updateData['provider'] = null;
                        $updateData['geocoding_status'] = Place::STATUS_PENDING;
                        $coordinatesChanged = $oldLat !== null || $oldLng !== null;
                    }
                }

                if (! empty($updateData)) {
                    $place = $this->placeRepository->update($place, $updateData);
                }

                if ($coordinatesChanged) {
                    $this->recordAudit($place, $oldLat, $oldLng, $place->latitude, $place->longitude);
                }

                if ($place->geocoding_status === Place::STATUS_PENDING) {
                    GeocodePlaceJob::dispatch($place->id);
                }

                return $place;
            });
        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), 500);
        }
    }

    public function delete(int $id): void
    {
        $place = $this->placeRepository->getById($id);
        $this->placeRepository->delete($place);
    }

    public function resolveGeocoding(Place $place): Place
    {
        if ($place->geocoding_status === Place::STATUS_COMPLETED) {
            return $place;
        }

        $place->update(['geocoding_status' => Place::STATUS_PROCESSING]);

        $oldLat = $place->latitude;
        $oldLng = $place->longitude;

        $location = $this->geocodingService->geocode($place->address);

        if ($location === null) {
            $place->update(['geocoding_status' => Place::STATUS_FAILED]);

            return $place->fresh();
        }

        $place->update([
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'provider' => $location->provider,
            'geocoding_status' => Place::STATUS_COMPLETED,
        ]);

        if ($oldLat !== $location->latitude || $oldLng !== $location->longitude) {
            $this->recordAudit($place->fresh(), $oldLat, $oldLng, $location->latitude, $location->longitude);
        }

        return $place->fresh();
    }

    protected function recordAudit(
        Place $place,
        ?float $oldLat,
        ?float $oldLng,
        ?float $newLat,
        ?float $newLng,
    ): void {
        PlaceAudit::query()->create([
            'place_id' => $place->id,
            'old_lat' => $oldLat,
            'old_lng' => $oldLng,
            'new_lat' => $newLat,
            'new_lng' => $newLng,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }
}
