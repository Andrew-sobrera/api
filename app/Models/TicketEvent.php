<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketEvent extends Model
{
    protected $fillable = ['event_id', 'name', 'price', 'quantity'];
}
