<?php

namespace App\Enums;

enum AsaasAccountStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case REJECTED = 'REJECTED';
    case INCOMPLETE = 'INCOMPLETE';
}
