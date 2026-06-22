<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Jobs\GenerateTicketsJob;
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

        if ($this->isApprovedStatus($status)) {
            if ($order->payment_status?->value === 'PAID') {
                $this->auditService->record($paymentId, $eventName, $status, 'duplicate', $order->id, $payload);

                return;
            }

            $this->approvePayment($order);
            $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);

            return;
        }

        if ($this->isFailedStatus($status)) {
            $this->failPayment($order);
            $this->auditService->record($paymentId, $eventName, $status, 'processed', $order->id, $payload);
        }
    }

    private function approvePayment(Order $order): void
    {
        if ($order->payment_status?->value === 'PAID') {
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
        if (in_array($order->payment_status?->value, ['FAILED', 'PAID'], true)) {
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

    private function isApprovedStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'CONFIRMED',
            'RECEIVED',
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
        ], true);
    }

    private function isFailedStatus(string $status): bool
    {
        return in_array(strtoupper($status), [
            'FAILED',
            'PAYMENT_FAILED',
            'REFUNDED',
            'OVERDUE',
        ], true);
    }
}
