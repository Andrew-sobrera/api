<?php

namespace App\Services;

use App\Enums\OrderChargebackStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Jobs\GenerateTicketsJob;
use App\Jobs\ProcessAsaasRefundJob;
use App\Jobs\SendPurchaseEmailJob;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
use App\Repositories\EventTicketRepository;
use App\Repositories\OrderRepository;
use App\Support\QueueNames;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected EventTicketRepository $eventTicketRepository,
        protected TicketAvailabilityCacheService $availabilityCache,
        protected PaymentWebhookAuditService $auditService,
    ) {
    }

    public function process(
        string $paymentId,
        string $status,
        ?int $orderId = null,
        ?string $eventName = null,
        ?array $payload = null,
    ): void {
        $order = $this->orderRepository->findByAsaasPaymentId($paymentId);

        if (! $order && $orderId) {
            $order = $this->orderRepository->findByIdIfExists($orderId);

            if ($order && ! $order->asaas_payment_id) {
                $this->orderRepository->updatePaymentData($order, [
                    'asaas_payment_id' => $paymentId,
                ]);
            }
        }

        if (! $order) {
            Log::warning('Order not found for Asaas payment', [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'status' => $status,
            ]);

            $this->auditService->record($paymentId, $eventName, $status, 'order_not_found', $orderId, $payload);

            return;
        }

        match (true) {
            $this->isApprovedStatus($status) => $this->handleApproved($order, $paymentId, $eventName, $status, $payload),
            $this->isRefundedStatus($status) => $this->handleRefunded($order, $paymentId, $eventName, $status, $payload),
            $this->isChargebackStatus($status) => $this->handleChargeback($order, $paymentId, $eventName, $status, $payload),
            $this->isFailedStatus($status) => $this->handleFailed($order, $paymentId, $eventName, $status, $payload),
            default => $this->auditService->record($paymentId, $eventName, $status, 'ignored', $order->id, $payload),
        };
    }

    // ─────────────────────────────────────────── Handlers ────

    private function handleApproved(Order $order, string $paymentId, ?string $eventName, string $status, ?array $payload): void
    {
        if ($order->payment_status?->value === PaymentStatus::PAID->value) {
            $this->auditService->record($paymentId, $eventName, $status, 'duplicate', $order->id, $payload);

            return;
        }

        $this->approvePayment($order);
        $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);
    }

    private function handleRefunded(Order $order, string $paymentId, ?string $eventName, string $status, ?array $payload): void
    {
        if (in_array($order->status?->value, [OrderStatus::CANCELLED->value], true)) {
            $this->auditService->record($paymentId, $eventName, $status, 'duplicate', $order->id, $payload);

            return;
        }

        $this->orderRepository->update($order, [
            'status' => OrderStatus::CANCELLED,
            'payment_status' => PaymentStatus::CANCELLED,
            'refunded_at' => now(),
        ]);

        // Libera o estoque
        $order->load('reservations');

        foreach ($order->reservations as $reservation) {
            if ($reservation->status === ReservationStatus::CONFIRMED || $reservation->status === ReservationStatus::RESERVED) {
                $this->releaseReservation($order, $reservation);
            }
        }

        Log::info('[PaymentWebhookService] Pedido estornado via webhook', ['order_id' => $order->id]);

        $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);
    }

    private function handleChargeback(Order $order, string $paymentId, ?string $eventName, string $status, ?array $payload): void
    {
        $chargebackStatus = match (strtoupper($status)) {
            'PAYMENT_CHARGEBACK_REQUESTED', 'CHARGEBACK_REQUESTED' => OrderChargebackStatus::REQUESTED,
            'PAYMENT_CHARGEBACK_DISPUTE', 'CHARGEBACK_DISPUTE' => OrderChargebackStatus::IN_DISPUTE,
            'PAYMENT_CHARGEBACK_REVERSED', 'CHARGEBACK_REVERSED' => OrderChargebackStatus::REVERSED,
            default => OrderChargebackStatus::REQUESTED,
        };

        $this->orderRepository->update($order, [
            'chargeback_status' => $chargebackStatus,
        ]);

        Log::warning('[PaymentWebhookService] Chargeback recebido', [
            'order_id' => $order->id,
            'chargeback_status' => $chargebackStatus->value,
            'asaas_event' => $eventName,
        ]);

        // Dispara processamento assíncrono de estorno se necessário
        if ($chargebackStatus === OrderChargebackStatus::REVERSED || $chargebackStatus === OrderChargebackStatus::DONE) {
            ProcessAsaasRefundJob::dispatch($order->id)
                ->onConnection(config('queue.default'))
                ->onQueue(QueueNames::ASAAS_REFUNDS);
        }

        $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);
    }

    private function handleFailed(Order $order, string $paymentId, ?string $eventName, string $status, ?array $payload): void
    {
        $this->failPayment($order);
        $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);
    }

    // ──────────────────────────────────────── Operações ────

    private function approvePayment(Order $order): void
    {
        if ($order->payment_status?->value === PaymentStatus::PAID->value) {
            return;
        }

        $this->orderRepository->confirmPayment($order);

        $order->load('reservations');

        foreach ($order->reservations as $reservation) {
            if ($reservation->status === ReservationStatus::RESERVED) {
                $this->orderRepository->updateReservation($reservation, [
                    'status' => ReservationStatus::CONFIRMED,
                ]);
            }
        }

        if ($order->reservation && $order->reservation->status === ReservationStatus::RESERVED) {
            $this->orderRepository->updateReservation($order->reservation, [
                'status' => ReservationStatus::CONFIRMED,
            ]);
        }

        GenerateTicketsJob::dispatch($order->id)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::TICKETS_GENERATION);

        SendPurchaseEmailJob::dispatch($order->id, PaymentApprovedMail::class)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::EMAILS);
    }

    private function failPayment(Order $order): void
    {
        if (in_array($order->payment_status?->value, [PaymentStatus::FAILED->value, PaymentStatus::PAID->value], true)) {
            return;
        }

        $this->orderRepository->failPayment($order);

        $order->load('reservations');

        $processed = [];

        foreach ($order->reservations as $reservation) {
            if ($reservation->status === ReservationStatus::RESERVED) {
                $this->releaseReservation($order, $reservation);
                $processed[] = $reservation->id;
            }
        }

        if ($order->reservation
            && $order->reservation->status === ReservationStatus::RESERVED
            && ! in_array($order->reservation->id, $processed, true)
        ) {
            $this->releaseReservation($order, $order->reservation);
        }

        SendPurchaseEmailJob::dispatch($order->id, PaymentFailedMail::class)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::EMAILS);
    }

    private function releaseReservation($order, $reservation): void
    {
        if ($reservation->batch_id) {
            $batch = \App\Models\TicketBatch::find($reservation->batch_id);

            if ($batch) {
                app(TicketBatchService::class)->incrementBatchStock($batch, $reservation->quantity);
            }
        } else {
            $ticket = $this->eventTicketRepository->releaseTickets(
                $reservation->event_ticket_id,
                $reservation->quantity
            );

            $this->availabilityCache->setAvailable($ticket->id, $ticket->quantity);
        }

        if ($reservation->seat_id) {
            app(SeatService::class)->releaseSeat($reservation->seat_id);
        }

        $this->orderRepository->updateReservation($reservation, [
            'status' => ReservationStatus::EXPIRED,
        ]);
    }

    // ────────────────────────────────────────── Helpers ────

    private function isApprovedStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'CONFIRMED', 'RECEIVED',
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED',
        ], true);
    }

    private function isRefundedStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'REFUNDED', 'PAYMENT_REFUNDED',
            'DELETED', 'PAYMENT_DELETED',
        ], true);
    }

    private function isChargebackStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'PAYMENT_CHARGEBACK_REQUESTED', 'CHARGEBACK_REQUESTED',
            'PAYMENT_CHARGEBACK_DISPUTE', 'CHARGEBACK_DISPUTE',
            'PAYMENT_CHARGEBACK_REVERSED', 'CHARGEBACK_REVERSED',
        ], true);
    }

    private function isFailedStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'FAILED', 'PAYMENT_FAILED',
            'OVERDUE', 'PAYMENT_OVERDUE',
        ], true);
    }
}
