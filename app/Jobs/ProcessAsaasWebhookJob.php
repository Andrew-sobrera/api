<?php

namespace App\Jobs;

use App\Services\PaymentWebhookService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAsaasWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public string $paymentId,
        public string $status
    ) {
        $this->onQueue(QueueNames::PAYMENTS_WEBHOOK);
    }

    public function handle(PaymentWebhookService $paymentWebhookService): void
    {
        $paymentWebhookService->process($this->paymentId, $this->status);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessAsaasWebhookJob failed permanently', [
            'payment_id' => $this->paymentId,
            'status' => $this->status,
            'message' => $exception?->getMessage(),
        ]);
    }
}
