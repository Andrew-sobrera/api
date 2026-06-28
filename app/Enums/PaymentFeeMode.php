<?php

namespace App\Enums;

enum PaymentFeeMode: string
{
    /** Comprador paga as taxas de processamento */
    case CUSTOMER = 'CUSTOMER';

    /** Produtor absorve as taxas de processamento */
    case PRODUCER = 'PRODUCER';
}
