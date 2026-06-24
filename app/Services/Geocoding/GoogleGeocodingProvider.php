<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingInterface;
use App\DTOs\LocationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleGeocodingProvider implements GeocodingInterface
{
    public function providerName(): string
    {
        return 'google';
    }

    public function geocode(string $address): ?LocationDTO
    {
        $apiKey = config('services.google_maps.key');

        if (! $apiKey) {
            Log::warning('Google Maps API key not configured; skipping geocoding.');

            return null;
        }

        try {
            $response = Http::timeout(config('services.geocoding.timeout', 10))->get(
                config('services.google_maps.geocode_url'),
                [
                    'address' => $address,
                    'key' => $apiKey,
                    'region' => config('services.google_maps.region'),
                    'language' => config('services.google_maps.language'),
                ]
            );

            if (! $response->successful()) {
                Log::warning('Google Geocoding API HTTP request failed.', [
                    'http_status' => $response->status(),
                    'address' => $address,
                ]);

                return null;
            }

            $payload = $response->json();
            $apiStatus = $payload['status'] ?? null;

            if ($apiStatus === 'OVER_QUERY_LIMIT') {
                throw new \RuntimeException('Google Geocoding rate limit exceeded.');
            }

            if ($apiStatus !== 'OK' || empty($payload['results'][0]['geometry']['location'])) {
                Log::info('Google Geocoding API returned no coordinates.', [
                    'status' => $apiStatus,
                    'address' => $address,
                ]);

                return null;
            }

            $location = $payload['results'][0]['geometry']['location'];

            return new LocationDTO(
                latitude: (float) $location['lat'],
                longitude: (float) $location['lng'],
                provider: $this->providerName(),
            );
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Google Geocoding API exception.', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);

            return null;
        }
    }
}
