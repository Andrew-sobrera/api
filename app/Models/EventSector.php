<?php

namespace App\Models;

use App\Enums\SectorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSector extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'color',
        'category',
        'sort_order',
        'pos_x',
        'pos_y',
        'map_visible',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'pos_x' => 'float',
            'pos_y' => 'float',
            'map_visible' => 'boolean',
            'status' => SectorStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(TicketEvent::class, 'sector_id');
    }

    public function seatRows(): HasMany
    {
        return $this->hasMany(SeatRow::class, 'sector_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'sector_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(TicketBatch::class, 'sector_id');
    }
}
