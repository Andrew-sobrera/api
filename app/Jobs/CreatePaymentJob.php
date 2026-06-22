<?php

namespace App\Jobs;

use App\Services\OrderPaymentService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public int $orderId
    ) {
        $this->onQueue(QueueNames::PAYMENTS_CREATE);
    }

    public function handle(OrderPaymentService $orderPaymentService): void
    {
        $orderPaymentService->processPayment($this->orderId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('CreatePaymentJob failed permanently', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
