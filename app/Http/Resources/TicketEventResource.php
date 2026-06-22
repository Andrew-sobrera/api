<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'sector_id' => $this->sector_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'sale_rules' => $this->sale_rules,
            'batches' => TicketBatchResource::collection($this->whenLoaded('batches')),
            'sector' => new EventSectorResource($this->whenLoaded('sector')),
        ];
    }
}
