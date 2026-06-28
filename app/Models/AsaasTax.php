<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsaasTax extends Model
{
    protected $fillable = [
        'payment_type',
        'installment_min',
        'installment_max',
        'fixed_fee',
        'percentage_fee',
        'valid_from',
        'valid_until',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'installment_min' => 'integer',
            'installment_max' => 'integer',
            'fixed_fee' => 'integer',
            'percentage_fee' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'active' => 'boolean',
        ];
    }
}
