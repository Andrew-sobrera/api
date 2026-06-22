<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id',
        'event_id',
        'event_ticket_id',
        'sector_id',
        'seat_id',
        'batch_id',
        'buyer_name',
        'buyer_email',
        'qr_code_url',
        'hash',
        'status',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'used_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class, 'batch_id');
    }
}
