<?php

namespace App\Models;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    protected $fillable = [
        'event_id',
        'sector_id',
        'seat_row_id',
        'row_label',
        'seat_number',
        'label',
        'pos_x',
        'pos_y',
        'rotation',
        'width',
        'height',
        'seat_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
            'rotation' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'seat_type' => SeatType::class,
            'status' => SeatStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(EventSector::class, 'sector_id');
    }

    public function seatRow(): BelongsTo
    {
        return $this->belongsTo(SeatRow::class, 'seat_row_id');
    }
}
