<?php

namespace App\Jobs;

use App\Services\CartExpirationService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireCartReservationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $cartId)
    {
        $this->onQueue(QueueNames::TICKETS_EXPIRATION);
    }

    public function handle(CartExpirationService $cartExpirationService): void
    {
        $cartExpirationService->expireCart($this->cartId);
    }
}
