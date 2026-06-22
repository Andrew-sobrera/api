<?php

namespace App\Enums;

enum BatchStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SOLD_OUT = 'sold_out';
    case EXPIRED = 'expired';
}
