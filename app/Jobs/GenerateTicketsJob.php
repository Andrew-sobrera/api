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
        $this->onQueue(QueueNames::TICKETS_GENERATION);
    }

    public function handle(TicketGenerationService $ticketGenerationService): void
    {
        $order = \App\Models\Order::findOrFail($this->orderId);
        $ticketGenerationService->generateForOrder($order);

        SendTicketsEmailJob::dispatch($this->orderId)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::EMAILS);
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
