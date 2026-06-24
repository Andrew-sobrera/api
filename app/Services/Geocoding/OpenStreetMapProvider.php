<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingInterface;
use App\DTOs\LocationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenStreetMapProvider implements GeocodingInterface
{
    public function providerName(): string
    {
        return 'osm';
    }

    public function geocode(string $address): ?LocationDTO
    {
        try {
            $response = Http::timeout(config('services.geocoding.timeout', 10))
                ->withHeaders([
                    'User-Agent' => config('services.geocoding.osm_user_agent'),
                ])
                ->get(config('services.geocoding.osm_url'), [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => config('services.geocoding.osm_country_codes', 'br'),
                ]);

            if (! $response->successful()) {
                Log::warning('OpenStreetMap Nominatim request failed.', [
                    'http_status' => $response->status(),
                    'address' => $address,
                ]);

                return null;
            }

            $results = $response->json();

            if (empty($results[0]['lat']) || empty($results[0]['lon'])) {
                Log::info('OpenStreetMap Nominatim returned no coordinates.', [
                    'address' => $address,
                ]);

                return null;
            }

            return new LocationDTO(
                latitude: (float) $results[0]['lat'],
                longitude: (float) $results[0]['lon'],
                provider: $this->providerName(),
            );
        } catch (\Throwable $e) {
            Log::error('OpenStreetMap Nominatim exception.', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);

            return null;
        }
    }
}
