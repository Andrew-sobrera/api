<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TicketEventResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date,
            'category' => $this->category,
            'status' => $this->status,
            'ticket_type' => $this->ticket_type,
            'tickets' => TicketEventResource::collection($this->tickets),
        ];
    }
}
