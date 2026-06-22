<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_event_id' => $this->ticket_event_id,
            'sector_id' => $this->sector_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'sold_quantity' => $this->sold_quantity,
            'available_quantity' => $this->availableQuantity(),
            'price' => $this->price,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
        ];
    }
}
