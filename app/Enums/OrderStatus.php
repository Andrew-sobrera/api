<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case PAYMENT_FAILED = 'PAYMENT_FAILED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
