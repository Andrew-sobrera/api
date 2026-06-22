<?php

namespace App\Models;

use Database\Factories\TicketEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketEvent extends Model
{
    /** @use HasFactory<TicketEventFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'sector_id',
        'name',
        'description',
        'price',
        'quantity',
        'status',
        'sale_rules',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'status' => \App\Enums\TicketEventStatus::class,
            'sale_rules' => 'array',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function sector()
    {
        return $this->belongsTo(EventSector::class, 'sector_id');
    }

    public function batches()
    {
        return $this->hasMany(TicketBatch::class, 'ticket_event_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'event_ticket_id');
    }

    public function reservations()
    {
        return $this->hasMany(TicketReservation::class, 'event_ticket_id');
    }
}
