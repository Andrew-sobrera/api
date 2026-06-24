<?php

namespace App\Support;

class QueueNames
{
    public const PAYMENTS_CREATE = 'payments.create';

    public const PAYMENTS_WEBHOOK = 'payments.webhook';

    public const TICKETS_EXPIRATION = 'tickets.expiration';

    public const EMAILS = 'emails';

    public const TICKETS_GENERATION = 'tickets.generation';

    public const GEOCODING = 'geocoding';
}
