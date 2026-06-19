<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['name', 'date', 'category', 'status', 'ticket_type', 'slug'];

    public function tickets()
    {
        return $this->hasMany(TicketEvent::class);
    }
}
