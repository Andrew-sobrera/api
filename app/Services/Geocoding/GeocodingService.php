<?php

namespace App\Services\Geocoding;

use App\DTOs\LocationDTO;
use App\Models\GeocodeCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class GeocodingService
{
    public function __construct(
        protected GoogleGeocodingProvider $googleProvider,
        protected OpenStreetMapProvider $osmProvider,
    ) {
    }

    public function normalizeAddress(string $address): string
    {
        $normalized = Str::of($address)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->toString();

        return $normalized;
    }

    public function resolveFromCache(string $address): ?LocationDTO
    {
        $normalized = $this->normalizeAddress($address);

        if ($normalized === '') {
            return null;
        }

        $cached = GeocodeCache::query()
            ->where('address_normalized', $normalized)
            ->first();

        if (! $cached) {
            return null;
        }

        return new LocationDTO(
            latitude: (float) $cached->latitude,
            longitude: (float) $cached->longitude,
            provider: $cached->provider,
        );
    }

    public function geocode(string $address): ?LocationDTO
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        $cached = $this->resolveFromCache($address);

        if ($cached !== null) {
            return $cached;
        }

        $location = $this->geocodeWithProviders($address);

        if ($location !== null) {
            $this->storeInCache($address, $location);
        }

        return $location;
    }

    public function storeInCache(string $address, LocationDTO $location): void
    {
        $normalized = $this->normalizeAddress($address);

        if ($normalized === '') {
            return;
        }

        GeocodeCache::query()->updateOrCreate(
            ['address_normalized' => $normalized],
            [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'provider' => $location->provider,
            ]
        );
    }

    protected function geocodeWithProviders(string $address): ?LocationDTO
    {
        if ($this->canUseGoogle()) {
            try {
                $location = $this->googleProvider->geocode($address);

                if ($location !== null) {
                    $this->recordGoogleUsage();

                    return $location;
                }
            } catch (\RuntimeException $e) {
                Log::warning('Google geocoding unavailable, falling back to OSM.', [
                    'message' => $e->getMessage(),
                    'address' => $address,
                ]);
            }
        } else {
            Log::info('Google geocoding rate limit reached, using OSM fallback.', [
                'address' => $address,
            ]);
        }

        return $this->osmProvider->geocode($address);
    }

    protected function canUseGoogle(): bool
    {
        if (! config('services.google_maps.key')) {
            return false;
        }

        $limit = (int) config('services.geocoding.google_rate_limit_per_minute', 50);

        return ! RateLimiter::tooManyAttempts($this->googleRateLimitKey(), $limit);
    }

    protected function recordGoogleUsage(): void
    {
        RateLimiter::hit($this->googleRateLimitKey(), 60);
    }

    protected function googleRateLimitKey(): string
    {
        return 'geocoding:google:'.now()->format('Y-m-d-H-i');
    }
}
