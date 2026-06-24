<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    protected $fillable = [
        'address_normalized',
        'latitude',
        'longitude',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
