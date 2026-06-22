<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PIX = 'PIX';
    case CREDIT_CARD = 'CREDIT_CARD';
}
