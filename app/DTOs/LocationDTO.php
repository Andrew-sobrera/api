<?php

namespace App\DTOs;

readonly class LocationDTO
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $provider,
    ) {
    }

    /**
     * @return array{latitude: float, longitude: float, provider: string}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'provider' => $this->provider,
        ];
    }
}
