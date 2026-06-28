<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProducerPaymentMethod extends Model
{
    protected $fillable = [
        'producer_id',
        'payment_method',
        'max_installments',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'max_installments' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }
}
