<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['name', 'date', 'category', 'status', 'ticket_type', 'slug', 'banner_url', 'banner_public_id'];

    protected $hidden = [
        'banner_public_id',
    ];

    public function tickets()
    {
        return $this->hasMany(TicketEvent::class);
    }
}
