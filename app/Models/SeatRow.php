<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatRow extends Model
{
    protected $fillable = [
        'sector_id',
        'name',
        'sort_order',
        'pos_x',
        'pos_y',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(EventSector::class, 'sector_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'seat_row_id');
    }
}
