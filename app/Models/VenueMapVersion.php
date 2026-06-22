<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueMapVersion extends Model
{
    protected $fillable = [
        'venue_map_id',
        'user_id',
        'version_number',
        'label',
        'snapshot',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function venueMap(): BelongsTo
    {
        return $this->belongsTo(VenueMap::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
