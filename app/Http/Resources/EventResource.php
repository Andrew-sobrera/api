<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'date' => $this->date,
            'location' => $this->location,
            'category' => $this->category,
            'status' => $this->status,
            'ticket_type' => $this->ticket_type,
            'has_seats' => $this->has_seats,
            'slug' => $this->slug,
            'banner_url' => $this->banner_url,
            'tickets' => TicketEventResource::collection($this->whenLoaded('tickets')),
            'sectors' => EventSectorResource::collection($this->whenLoaded('sectors')),
        ];
    }
}
