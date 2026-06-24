<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producer extends Model
{
    protected $fillable = [
        'name',
        'cnpj',
    ];
}
