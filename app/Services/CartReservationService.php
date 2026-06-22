<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Models\TicketReservation;
use App\Repositories\EventTicketRepository;
use App\Repositories\OrderRepository;

class CartReservationService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected EventTicketRepository $eventTicketRepository,
        protected TicketAvailabilityCacheService $availabilityCache,
        protected TicketBatchService $batchService,
        protected SeatService $seatService,
    ) {
    }

    public function reserveForItem(Cart $cart, CheckoutLineItem $line): TicketReservation
    {
        if (! $this->availabilityCache->reserve($line->eventTicketId, $line->quantity)) {
            throw new InsufficientStockException();
        }

        try {
            $ticket = $this->eventTicketRepository->findForUpdate($line->eventTicketId);

            if ($line->batchId) {
                $batch = TicketBatch::query()->lockForUpdate()->findOrFail($line->batchId);

                if ($batch->availableQuantity() < $line->quantity) {
                    throw new InsufficientStockException();
                }

                $this->batchService->decrementBatchStock($batch, $line->quantity);
            } elseif ($ticket->quantity < $line->quantity) {
                throw new InsufficientStockException();
            }

            if ($line->seatId) {
                $this->seatService->reserveSeat($line->seatId);
            }

            $reservation = $this->orderRepository->createReservation([
                'event_ticket_id' => $line->eventTicketId,
                'cart_id' => $cart->id,
                'seat_id' => $line->seatId,
                'batch_id' => $line->batchId,
                'quantity' => $line->quantity,
                'status' => ReservationStatus::RESERVED,
                'expires_at' => $cart->expires_at,
            ]);

            if (! $line->batchId) {
                $updatedTicket = $this->eventTicketRepository->decrementQuantity($ticket, $line->quantity);
                $this->availabilityCache->setAvailable($ticket->id, $updatedTicket->quantity);
            }

            return $reservation;
        } catch (\Throwable $exception) {
            $this->availabilityCache->release($line->eventTicketId, $line->quantity);

            throw $exception;
        }
    }

    public function releaseReservation(TicketReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::RESERVED) {
            return;
        }

        if ($reservation->batch_id) {
            $batch = TicketBatch::find($reservation->batch_id);

            if ($batch) {
                $this->batchService->incrementBatchStock($batch, $reservation->quantity);
            }
        } else {
            $ticket = $this->eventTicketRepository->releaseTickets(
                $reservation->event_ticket_id,
                $reservation->quantity
            );

            $this->availabilityCache->setAvailable($ticket->id, $ticket->quantity);
        }

        if ($reservation->seat_id) {
            $this->seatService->releaseSeat($reservation->seat_id);
        }

        $this->orderRepository->updateReservation($reservation, [
            'status' => ReservationStatus::EXPIRED,
        ]);
    }

    public function releaseForCartItem(CartItem $item): void
    {
        $reservation = TicketReservation::query()
            ->where('cart_id', $item->cart_id)
            ->where('event_ticket_id', $item->event_ticket_id)
            ->when($item->batch_id, fn ($q) => $q->where('batch_id', $item->batch_id))
            ->when(! $item->batch_id, fn ($q) => $q->whereNull('batch_id'))
            ->when($item->seat_id, fn ($q) => $q->where('seat_id', $item->seat_id))
            ->when(! $item->seat_id, fn ($q) => $q->whereNull('seat_id'))
            ->where('status', ReservationStatus::RESERVED)
            ->first();

        if ($reservation) {
            $this->releaseReservation($reservation);
        }
    }

    public function transferCartToOrder(Cart $cart, Order $order): void
    {
        $expiresAt = now()->addMinutes(config('checkout.reservation_ttl_minutes'));

        TicketReservation::query()
            ->where('cart_id', $cart->id)
            ->where('status', ReservationStatus::RESERVED)
            ->each(function (TicketReservation $reservation) use ($order, $expiresAt) {
                $this->orderRepository->updateReservation($reservation, [
                    'order_id' => $order->id,
                    'cart_id' => null,
                    'expires_at' => $expiresAt,
                ]);
            });
    }
}
