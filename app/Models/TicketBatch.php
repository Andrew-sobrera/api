<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketBatch extends Model
{
    protected $fillable = [
        'ticket_event_id',
        'sector_id',
        'name',
        'quantity',
        'sold_quantity',
        'price',
        'starts_at',
        'ends_at',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sold_quantity' => 'integer',
            'price' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BatchStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function ticketEvent(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(EventSector::class, 'sector_id');
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->sold_quantity);
    }
}
