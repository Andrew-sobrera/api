<?php

namespace App\Enums;

enum TicketEventStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
