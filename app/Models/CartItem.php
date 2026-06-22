<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'event_id',
        'event_ticket_id',
        'sector_id',
        'batch_id',
        'seat_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventTicket(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'event_ticket_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(EventSector::class, 'sector_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class, 'batch_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_id');
    }
}
