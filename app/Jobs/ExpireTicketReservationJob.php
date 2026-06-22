<?php

namespace App\Jobs;

use App\Services\TicketReservationService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireTicketReservationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $reservationId
    ) {
        $this->onQueue(QueueNames::TICKETS_EXPIRATION);
    }

    public function handle(TicketReservationService $ticketReservationService): void
    {
        $ticketReservationService->expireReservation($this->reservationId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ExpireTicketReservationJob failed permanently', [
            'reservation_id' => $this->reservationId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
