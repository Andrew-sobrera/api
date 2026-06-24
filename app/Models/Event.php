<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToProducer;
use App\Models\Scopes\ProducerScope;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, BelongsToProducer;

    protected $fillable = [
        'name',
        'producer_id',
        'description',
        'date',
        'location',
        'location_name',
        'address',
        'latitude',
        'longitude',
        'place_id',
        'category',
        'status',
        'ticket_type',
        'has_seats',
        'slug',
        'banner_url',
        'banner_public_id',
    ];

    protected function casts(): array
    {
        return [
            'has_seats' => 'boolean',
            'date' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new ProducerScope());
    }

    protected $hidden = [
        'banner_public_id',
    ];

    public function venueMap()
    {
        return $this->hasOne(VenueMap::class);
    }

    public function tickets()
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function sectors()
    {
        return $this->hasMany(EventSector::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function issuedTickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }
}
