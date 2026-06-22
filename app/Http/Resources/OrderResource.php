<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'pix_payload' => $this->pix_payload,
            'pix_qr_code_url' => $this->pix_qr_code_url,
            'created_at' => $this->created_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'event' => new EventResource($this->whenLoaded('event')),
            'tickets' => TicketResource::collection($this->whenLoaded('issuedTickets')),
        ];
    }
}
