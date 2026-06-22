<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case RESERVED = 'RESERVED';
    case CONFIRMED = 'CONFIRMED';
    case EXPIRED = 'EXPIRED';
}
