<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'date',
        'location',
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
        ];
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
}
