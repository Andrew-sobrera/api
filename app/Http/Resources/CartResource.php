<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items, collect());
        $itemCount = $items->sum('quantity');
        $totalAmount = $items->sum(fn ($item) => $item->unit_price * $item->quantity);

        return [
            'cart_id' => $this->uuid,
            'status' => $this->status,
            'event_id' => $this->event_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expires_in_seconds' => $this->expires_at
                ? max(0, now()->diffInSeconds($this->expires_at, false))
                : 0,
            'item_count' => $itemCount,
            'total_amount' => $totalAmount,
            'items' => CartItemResource::collection($items),
        ];
    }
}
