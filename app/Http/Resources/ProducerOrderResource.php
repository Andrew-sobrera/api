<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProducerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'payment_method' => $this->payment_method?->value,
            'asaas_payment_id' => $this->asaas_payment_id,
            'chargeback_status' => $this->chargeback_status?->value,

            // Financeiro
            'ticket_amount' => round($this->ticket_amount / 100, 2),
            'gateway_fee' => round($this->gateway_fee / 100, 2),
            'platform_commission' => round($this->platform_commission / 100, 2),
            'producer_amount' => round($this->producer_amount / 100, 2),
            'total_amount' => round($this->total_amount / 100, 2),
            'installments' => $this->installments,

            // Comprador
            'buyer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),

            // Evento
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'date' => $this->event->date,
            ]),

            // Itens
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'ticket_name' => $item->eventTicket?->name,
                'quantity' => $item->quantity,
                'unit_price' => round($item->unit_price / 100, 2),
                'total_price' => round($item->total_price / 100, 2),
            ])),

            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
