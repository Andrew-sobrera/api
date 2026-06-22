<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'total_amount',
        'payment_method',
        'payment_status',
        'asaas_payment_id',
        'pix_payload',
        'pix_qr_code_url',
        'payment_response',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'total_amount' => 'integer',
            'payment_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(TicketReservation::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TicketReservation::class);
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
