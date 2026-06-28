<?php

namespace App\Models;

use App\Enums\OrderChargebackStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentFeeMode;
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
        'ticket_amount',
        'gateway_fee',
        'platform_commission',
        'producer_amount',
        'installments',
        'payment_fee_mode',
        'payment_method',
        'payment_status',
        'asaas_payment_id',
        'pix_payload',
        'pix_qr_code_url',
        'payment_response',
        'chargeback_status',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'payment_fee_mode' => PaymentFeeMode::class,
            'chargeback_status' => OrderChargebackStatus::class,
            'total_amount' => 'integer',
            'ticket_amount' => 'integer',
            'gateway_fee' => 'integer',
            'platform_commission' => 'integer',
            'producer_amount' => 'integer',
            'installments' => 'integer',
            'payment_response' => 'array',
            'refunded_at' => 'datetime',
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
