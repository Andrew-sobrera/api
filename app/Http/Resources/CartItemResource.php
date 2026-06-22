<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_ticket_id' => $this->event_ticket_id,
            'sector_id' => $this->sector_id,
            'batch_id' => $this->batch_id,
            'seat_id' => $this->seat_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->unit_price * $this->quantity,
            'ticket' => new TicketEventResource($this->whenLoaded('eventTicket')),
            'sector' => new EventSectorResource($this->whenLoaded('sector')),
            'seat' => new SeatResource($this->whenLoaded('seat')),
            'batch' => new TicketBatchResource($this->whenLoaded('batch')),
        ];
    }
}
