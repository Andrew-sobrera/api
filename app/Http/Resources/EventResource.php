<?php

namespace App\Http\Resources;

use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocationService $locationService */
        $locationService = app(LocationService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'date' => $this->date,
            'location' => $this->location,
            'location_name' => $this->location_name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'google_maps_url' => $locationService->buildGoogleMapsUrl(
                $this->latitude !== null ? (float) $this->latitude : null,
                $this->longitude !== null ? (float) $this->longitude : null,
            ),
            'waze_url' => $locationService->buildWazeUrl(
                $this->latitude !== null ? (float) $this->latitude : null,
                $this->longitude !== null ? (float) $this->longitude : null,
            ),
            'place_id' => $this->place_id,
            'category' => $this->category,
            'status' => $this->status,
            'ticket_type' => $this->ticket_type,
            'has_seats' => $this->has_seats,
            'slug' => $this->slug,
            'banner_url' => $this->banner_url,
            'producer' => new ProducerResource($this->whenLoaded('producer')),
            'tickets' => TicketEventResource::collection($this->whenLoaded('tickets')),
            'sectors' => EventSectorResource::collection($this->whenLoaded('sectors')),
        ];
    }
}
