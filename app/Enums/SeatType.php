<?php

namespace App\Enums;

enum SeatType: string
{
    case STANDARD = 'standard';
    case PCD = 'pcd';
    case COMPANION = 'companion';
    case BLOCKED = 'blocked';
    case VIP = 'vip';
    case TABLE = 'table';
    case BOOTH = 'booth';
}
