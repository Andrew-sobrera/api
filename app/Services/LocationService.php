<?php

namespace App\Services;

use App\Services\Geocoding\GeocodingService;

class LocationService
{
    public function __construct(
        protected GeocodingService $geocodingService,
    ) {
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocode(string $address): ?array
    {
        $location = $this->geocodingService->geocode($address);

        if ($location === null) {
            return null;
        }

        return [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ];
    }

    public function buildGoogleMapsUrl(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return sprintf(
            'https://www.google.com/maps/search/?api=1&query=%s,%s',
            $latitude,
            $longitude
        );
    }

    public function buildWazeUrl(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return sprintf(
            'https://waze.com/ul?ll=%s,%s&navigate=yes',
            $latitude,
            $longitude
        );
    }
}
