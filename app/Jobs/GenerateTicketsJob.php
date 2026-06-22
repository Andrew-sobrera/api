<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\TicketGenerationService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTicketsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300];

    public function __construct(public int $orderId)
    {
        $this->onQueue(QueueNames::TICKETS_GENERATION);
    }

    public function handle(TicketGenerationService $ticketGenerationService): void
    {
        $order = Order::with(['user', 'items'])->findOrFail($this->orderId);

        Log::info('GenerateTicketsJob started', [
            'order_id' => $this->orderId,
            'user_id' => $order->user_id,
            'buyer_email' => $order->user?->email,
        ]);

        $ticketGenerationService->generateForOrder($order);

        SendTicketsEmailJob::dispatch($this->orderId)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::EMAILS);

        Log::info('GenerateTicketsJob completed', [
            'order_id' => $this->orderId,
            'tickets_count' => $order->issuedTickets()->count(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateTicketsJob failed permanently', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
