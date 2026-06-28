<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketReservation;

class OrderRepository
{
    public function __construct(
        protected Order $orderModel,
        protected OrderItem $orderItemModel,
        protected TicketReservation $reservationModel
    ) {
    }

    public function createOrder(array $data): Order
    {
        return $this->orderModel->create($data);
    }

    public function create(array $data): Order
    {
        return $this->createOrder($data);
    }

    public function createItem(array $data): OrderItem
    {
        return $this->orderItemModel->create($data);
    }

    public function createReservation(array $data): TicketReservation
    {
        return $this->reservationModel->create($data);
    }

    public function findById(int $id): Order
    {
        return $this->orderModel
            ->with(['user', 'items.eventTicket', 'reservation', 'reservations', 'event', 'issuedTickets'])
            ->findOrFail($id);
    }

    public function findByIdIfExists(int $id): ?Order
    {
        return $this->orderModel
            ->with(['user', 'items.eventTicket', 'reservation', 'reservations'])
            ->find($id);
    }

    public function findByAsaasPaymentId(string $paymentId): ?Order
    {
        return $this->orderModel
            ->with(['user', 'items.eventTicket', 'reservation', 'reservations'])
            ->where('asaas_payment_id', $paymentId)
            ->first();
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh(['user', 'items.eventTicket', 'reservation']);
    }

    public function updatePaymentData(Order $order, array $data): Order
    {
        return $this->update($order, $data);
    }

    public function confirmPayment(Order $order): Order
    {
        return $this->update($order, [
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PAID,
        ]);
    }

    public function failPayment(Order $order): Order
    {
        return $this->update($order, [
            'payment_status' => PaymentStatus::FAILED,
            'status' => OrderStatus::PAYMENT_FAILED,
        ]);
    }

    public function updateReservation(TicketReservation $reservation, array $data): TicketReservation
    {
        $reservation->update($data);

        return $reservation->fresh();
    }

    public function findExpiredReservations()
    {
        return $this->reservationModel
            ->with(['eventTicket', 'order'])
            ->where('status', 'RESERVED')
            ->where('expires_at', '<', now())
            ->get();
    }

    public function findReservationById(int $id): TicketReservation
    {
        return $this->reservationModel
            ->with(['eventTicket', 'order'])
            ->findOrFail($id);
    }

    public function cancelOrder(Order $order): Order
    {
        return $this->update($order, [
            'status' => \App\Enums\OrderStatus::CANCELLED,
            'payment_status' => \App\Enums\PaymentStatus::CANCELLED,
        ]);
    }
}
