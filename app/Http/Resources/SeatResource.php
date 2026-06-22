<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'sector_id' => $this->sector_id,
            'row_label' => $this->row_label,
            'seat_number' => $this->seat_number,
            'label' => $this->label,
            'status' => $this->status,
        ];
    }
}
