<?php

namespace App\Http\Resources;

use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocationService $locationService */
        $locationService = app(LocationService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'provider' => $this->provider,
            'geocoding_status' => $this->geocoding_status,
            'google_maps_url' => $locationService->buildGoogleMapsUrl(
                $this->latitude !== null ? (float) $this->latitude : null,
                $this->longitude !== null ? (float) $this->longitude : null,
            ),
            'waze_url' => $locationService->buildWazeUrl(
                $this->latitude !== null ? (float) $this->latitude : null,
                $this->longitude !== null ? (float) $this->longitude : null,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
