<?php

namespace App\Console\Commands;

use App\Services\TicketReservationService;
use Illuminate\Console\Command;

class ExpireDueReservationsCommand extends Command
{
    protected $signature = 'reservations:expire-due';

    protected $description = 'Expire ticket reservations past their TTL (fallback scheduler)';

    public function handle(TicketReservationService $ticketReservationService): int
    {
        $ticketReservationService->expireDueReservations();

        $this->info('Expired due ticket reservations.');

        return self::SUCCESS;
    }
}
