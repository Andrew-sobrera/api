<?php

namespace App\Jobs;

use App\Services\TicketGenerationService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTicketsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
    }

    public function handle(TicketGenerationService $ticketGenerationService): void
    {
        $order = \App\Models\Order::findOrFail($this->orderId);
        $ticketGenerationService->generateForOrder($order);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function viaQueue(): string
    {
        return QueueNames::TICKETS_GENERATION;
    }
}
