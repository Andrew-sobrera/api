<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueMap extends Model
{
    protected $fillable = [
        'event_id',
        'venue_id',
        'name',
        'floor_plan_url',
        'floor_plan_opacity',
        'floor_plan_scale_x',
        'floor_plan_scale_y',
        'floor_plan_locked',
        'floor_plan_visible',
        'width',
        'height',
        'stage_label',
        'stage_x',
        'stage_y',
        'stage_width',
        'stage_height',
        'markers',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'stage_x' => 'integer',
            'stage_y' => 'integer',
            'stage_width' => 'integer',
            'stage_height' => 'integer',
            'floor_plan_opacity' => 'float',
            'floor_plan_scale_x' => 'float',
            'floor_plan_scale_y' => 'float',
            'floor_plan_locked' => 'boolean',
            'floor_plan_visible' => 'boolean',
            'markers' => 'array',
            'version' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function mapElements(): HasMany
    {
        return $this->hasMany(MapElement::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VenueMapVersion::class);
    }
}
