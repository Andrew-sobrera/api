<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\TicketBatch;
use App\Repositories\EventTicketRepository;
use App\Repositories\OrderRepository;

class TicketReservationService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected EventTicketRepository $eventTicketRepository,
        protected TicketAvailabilityCacheService $availabilityCache,
        protected TicketBatchService $batchService,
        protected SeatService $seatService,
    ) {
    }

    public function expireReservation(int $reservationId): void
    {
        $reservation = $this->orderRepository->findReservationById($reservationId);

        if ($reservation->status !== ReservationStatus::RESERVED) {
            return;
        }

        if ($reservation->expires_at->isFuture()) {
            return;
        }

        $order = $reservation->order;

        if ($order && $order->status === OrderStatus::PAID) {
            return;
        }

        if ($reservation->batch_id) {
            $batch = TicketBatch::find($reservation->batch_id);

            if ($batch) {
                $this->batchService->incrementBatchStock($batch, $reservation->quantity);
            }
        } else {
            $ticket = $this->eventTicketRepository->findById($reservation->event_ticket_id);
            $updatedTicket = $this->eventTicketRepository->incrementQuantity($ticket, $reservation->quantity);
            $this->availabilityCache->setAvailable($ticket->id, $updatedTicket->quantity);
        }

        if ($reservation->seat_id) {
            $this->seatService->releaseSeat($reservation->seat_id);
        }

        $this->orderRepository->updateReservation($reservation, [
            'status' => ReservationStatus::EXPIRED,
        ]);

        if ($order && $order->status === OrderStatus::PENDING_PAYMENT) {
            $this->orderRepository->update($order, [
                'status' => OrderStatus::EXPIRED,
            ]);
        }
    }

    public function expireDueReservations(): void
    {
        $reservations = $this->orderRepository->findExpiredReservations();

        foreach ($reservations as $reservation) {
            $this->expireReservation($reservation->id);
        }
    }
}
