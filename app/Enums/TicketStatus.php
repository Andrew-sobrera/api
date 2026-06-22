<?php

namespace App\Enums;

enum TicketStatus: string
{
    case GENERATED = 'generated';
    case USED = 'used';
    case CANCELLED = 'cancelled';
}
