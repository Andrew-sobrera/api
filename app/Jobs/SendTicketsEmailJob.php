<?php

namespace App\Jobs;

use App\Mail\TicketsReadyMail;
use App\Models\Order;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTicketsEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId)
    {
        $this->onQueue(QueueNames::EMAILS);
    }

    public function handle(): void
    {
        $order = Order::with([
            'user',
            'event',
            'issuedTickets.eventTicket',
            'issuedTickets.event',
            'issuedTickets.sector',
            'issuedTickets.seat',
        ])->findOrFail($this->orderId);

        if ($order->issuedTickets->isEmpty()) {
            return;
        }

        Mail::to($order->user->email)->send(new TicketsReadyMail($order));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendTicketsEmailJob failed permanently', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
