<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Payments\AsaasRefundService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAsaasRefundJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 120, 300];

    public function __construct(public int $orderId)
    {
        $this->onQueue(QueueNames::ASAAS_REFUNDS);
    }

    public function handle(AsaasRefundService $refundService): void
    {
        $order = Order::with(['user', 'event', 'reservations'])->findOrFail($this->orderId);

        Log::info('[ProcessAsaasRefundJob] Processando estorno', [
            'order_id' => $this->orderId,
            'asaas_payment_id' => $order->asaas_payment_id,
        ]);

        $refundService->refundOrder($order, 'Chargeback processado via webhook');

        Log::info('[ProcessAsaasRefundJob] Estorno processado', [
            'order_id' => $this->orderId,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[ProcessAsaasRefundJob] Falha permanente ao processar estorno', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
