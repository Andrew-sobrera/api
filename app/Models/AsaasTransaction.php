<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsaasTransaction extends Model
{
    protected $fillable = [
        'asaas_payment_id',
        'asaas_customer_id',
        'producer_id',
        'order_id',
        'event_id',
        'type',
        'status',
        'amount',
        'fee_amount',
        'net_amount',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
