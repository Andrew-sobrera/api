<?php

namespace App\Enums;

enum OrderChargebackStatus: string
{
    case REQUESTED = 'REQUESTED';
    case IN_DISPUTE = 'IN_DISPUTE';
    case REVERSED = 'REVERSED';
    case DONE = 'DONE';
}
