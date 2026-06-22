<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'event_id' => $this->event_id,
            'event_ticket_id' => $this->event_ticket_id,
            'sector_id' => $this->sector_id,
            'seat_id' => $this->seat_id,
            'batch_id' => $this->batch_id,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'qr_code_url' => $this->qr_code_url,
            'hash' => $this->hash,
            'status' => $this->status,
            'used_at' => $this->used_at,
            'event' => new EventResource($this->whenLoaded('event')),
            'ticket_type' => new TicketEventResource($this->whenLoaded('eventTicket')),
            'sector' => new EventSectorResource($this->whenLoaded('sector')),
            'seat' => new SeatResource($this->whenLoaded('seat')),
        ];
    }
}
