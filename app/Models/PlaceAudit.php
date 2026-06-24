<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'place_id',
        'old_lat',
        'old_lng',
        'new_lat',
        'new_lng',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_lat' => 'float',
            'old_lng' => 'float',
            'new_lat' => 'float',
            'new_lng' => 'float',
            'changed_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
