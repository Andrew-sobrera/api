<?php

namespace App\Services;

use App\Models\PaymentWebhookEvent;

class PaymentWebhookAuditService
{
    public function wasProcessed(string $paymentId, ?string $eventName): bool
    {
        return PaymentWebhookEvent::query()
            ->where('provider', 'asaas')
            ->where('payment_id', $paymentId)
            ->where('event_name', $eventName)
            ->whereIn('processing_result', ['accepted', 'processed', 'duplicate'])
            ->exists();
    }

    public function record(
        string $paymentId,
        ?string $eventName,
        ?string $mappedStatus,
        string $result,
        ?int $orderId = null,
        ?array $payload = null,
    ): PaymentWebhookEvent {
        return PaymentWebhookEvent::query()->updateOrCreate(
            [
                'provider' => 'asaas',
                'payment_id' => $paymentId,
                'event_name' => $eventName,
            ],
            [
                'mapped_status' => $mappedStatus,
                'order_id' => $orderId,
                'processing_result' => $result,
                'payload' => $payload,
            ]
        );
    }
}
