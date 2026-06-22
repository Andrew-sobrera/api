<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapElement extends Model
{
    protected $fillable = [
        'venue_map_id',
        'element_key',
        'type',
        'label',
        'pos_x',
        'pos_y',
        'rotation',
        'scale',
        'width',
        'height',
        'props',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
            'rotation' => 'integer',
            'scale' => 'float',
            'width' => 'integer',
            'height' => 'integer',
            'props' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function venueMap(): BelongsTo
    {
        return $this->belongsTo(VenueMap::class);
    }
}
