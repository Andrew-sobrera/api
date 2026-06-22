<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'tickets' => TicketEventResource::collection($this->whenLoaded('tickets')),
            'seats' => SeatResource::collection($this->whenLoaded('seats')),
            'batches' => TicketBatchResource::collection($this->whenLoaded('batches')),
        ];
    }
}
