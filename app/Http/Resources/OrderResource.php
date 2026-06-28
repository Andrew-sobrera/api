<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstItem = $this->relationLoaded('items') ? $this->items->first() : null;
        $reservation = $this->relationLoaded('reservations')
            ? $this->reservations->sortBy('expires_at')->first()
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'status' => $this->status?->value ?? $this->status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'total' => $this->total_amount,
            'total_amount' => $this->total_amount,
            'ticket_amount' => $this->ticket_amount,
            'gateway_fee' => $this->gateway_fee,
            'platform_commission' => $this->platform_commission,
            'producer_amount' => $this->producer_amount,
            'installments' => $this->installments,
            'payment_fee_mode' => $this->payment_fee_mode?->value ?? $this->payment_fee_mode,
            'event_name' => $this->whenLoaded('event', fn () => $this->event?->name),
            'ticket_name' => $firstItem?->eventTicket?->name,
            'quantity' => $this->whenLoaded('items', fn () => (int) $this->items->sum('quantity'), 0),
            'expires_at' => $reservation?->expires_at?->toIso8601String(),
            'pix_copy_paste' => $this->pix_payload,
            'pix_qr_code' => $this->pix_qr_code_url,
            'pix_payload' => $this->pix_payload,
            'pix_qr_code_url' => $this->pix_qr_code_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'event' => new EventResource($this->whenLoaded('event')),
            'tickets' => TicketResource::collection($this->whenLoaded('issuedTickets')),
        ];
    }
}
