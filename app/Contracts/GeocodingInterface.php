<?php

namespace App\Contracts;

use App\DTOs\LocationDTO;

interface GeocodingInterface
{
    public function geocode(string $address): ?LocationDTO;

    public function providerName(): string;
}
