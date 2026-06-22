<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Jobs\CreatePaymentJob;
use App\Jobs\ExpireTicketReservationJob;
use App\Jobs\SendPurchaseEmailJob;
use App\Mail\PurchaseCreatedMail;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Repositories\EventTicketRepository;
use App\Repositories\OrderRepository;
use App\Support\QueueNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    public function __construct(
        protected EventTicketRepository $eventTicketRepository,
        protected OrderRepository $orderRepository,
        protected OrderPaymentService $orderPaymentService,
        protected TicketAvailabilityCacheService $availabilityCache,
        protected CheckoutItemResolver $itemResolver,
        protected TicketBatchService $batchService,
        protected SeatService $seatService,
        protected CartService $cartService,
    ) {
    }

    /**
     * @param  CheckoutLineItem[]  $items
     */
    public function checkoutFromItems(
        int $userId,
        array $items,
        PaymentMethod $paymentMethod,
        ?string $cardToken = null,
        bool $clearCart = false,
        ?int $eventId = null,
    ): Order {
        if (empty($items)) {
            throw new InsufficientStockException('Carrinho vazio.');
        }

        foreach ($items as $item) {
            if (! $this->availabilityCache->reserve($item->eventTicketId, $item->quantity)) {
                throw new InsufficientStockException();
            }
        }

        try {
            $order = DB::transaction(function () use ($userId, $items, $paymentMethod) {
                $totalAmount = 0;
                $eventId = null;

                foreach ($items as $item) {
                    $totalAmount += $item->unitPrice * $item->quantity;
                    $ticket = $this->eventTicketRepository->findForUpdate($item->eventTicketId);
                    $eventId = $ticket->event_id;
                }

                $expiresAt = now()->addMinutes(config('checkout.reservation_ttl_minutes'));

                $order = $this->orderRepository->createOrder([
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_method' => $paymentMethod,
                    'total_amount' => $totalAmount,
                ]);

                foreach ($items as $item) {
                    $this->processLineItem($order, $item, $expiresAt);
                }

                return $order->load(['items.eventTicket', 'reservations', 'user']);
            });
        } catch (\Throwable $exception) {
            foreach ($items as $item) {
                $this->availabilityCache->release($item->eventTicketId, $item->quantity);
            }

            throw $exception;
        }

        try {
            $order = $this->orderPaymentService->processPayment($order->id, $cardToken);
        } catch (\Throwable $exception) {
            $this->rollbackFailedPayment($order, $items);
            Log::error('Checkout payment failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->dispatchPostCheckoutJobs($order);

        if ($clearCart) {
            $this->cartService->clear(\App\Models\User::findOrFail($userId), $eventId);
        }

        return $order;
    }

    public function checkout(
        int $userId,
        int $eventTicketId,
        int $quantity,
        PaymentMethod $paymentMethod,
        ?string $cardToken = null,
        ?int $batchId = null,
        ?int $seatId = null,
    ): Order {
        $items = $this->itemResolver->resolve([[
            'event_ticket_id' => $eventTicketId,
            'quantity' => $quantity,
            'batch_id' => $batchId,
            'seat_id' => $seatId,
        ]]);

        return $this->checkoutFromItems($userId, $items, $paymentMethod, $cardToken);
    }

    public function checkoutFromCart(int $userId, PaymentMethod $paymentMethod, ?string $cardToken = null, ?int $eventId = null): Order
    {
        $user = \App\Models\User::findOrFail($userId);
        $items = $this->cartService->toCheckoutItems($user, $eventId);

        if (! $eventId) {
            $eventId = \App\Models\TicketEvent::findOrFail($items[0]->eventTicketId)->event_id;
        }

        $cart = $this->cartService->activeCartForUserEvent($userId, $eventId);

        if (! $cart) {
            throw new InsufficientStockException('Carrinho expirado. Adicione os itens novamente.');
        }

        try {
            $order = DB::transaction(function () use ($userId, $items, $paymentMethod, $cart) {
                $totalAmount = 0;
                $eventId = null;

                foreach ($items as $item) {
                    $totalAmount += $item->unitPrice * $item->quantity;
                    $ticket = $this->eventTicketRepository->findForUpdate($item->eventTicketId);
                    $eventId = $ticket->event_id;
                }

                $order = $this->orderRepository->createOrder([
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_method' => $paymentMethod,
                    'total_amount' => $totalAmount,
                ]);

                foreach ($items as $item) {
                    $this->processLineItemFromCart($order, $item);
                }

                app(CartReservationService::class)->transferCartToOrder($cart, $order);

                return $order->load(['items.eventTicket', 'reservations', 'user']);
            });
        } catch (\Throwable $exception) {
            throw $exception;
        }

        try {
            $order = $this->orderPaymentService->processPayment($order->id, $cardToken);
        } catch (\Throwable $exception) {
            $this->rollbackFailedPayment($order, $items);
            Log::error('Checkout payment failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->dispatchPostCheckoutJobs($order);
        $this->cartService->clear($user, $eventId);

        return $order;
    }

    private function processLineItemFromCart(Order $order, CheckoutLineItem $item): void
    {
        $lineTotal = $item->unitPrice * $item->quantity;

        $this->orderRepository->createItem([
            'order_id' => $order->id,
            'event_ticket_id' => $item->eventTicketId,
            'sector_id' => $item->sectorId,
            'batch_id' => $item->batchId,
            'seat_id' => $item->seatId,
            'quantity' => $item->quantity,
            'unit_price' => $item->unitPrice,
            'total_price' => $lineTotal,
        ]);
    }

    private function processLineItem(Order $order, CheckoutLineItem $item, $expiresAt): void
    {
        $ticket = $this->eventTicketRepository->findForUpdate($item->eventTicketId);

        if ($item->batchId) {
            $batch = TicketBatch::query()->lockForUpdate()->findOrFail($item->batchId);

            if ($batch->availableQuantity() < $item->quantity) {
                throw new InsufficientStockException();
            }

            $this->batchService->decrementBatchStock($batch, $item->quantity);
        } elseif ($ticket->quantity < $item->quantity) {
            throw new InsufficientStockException();
        }

        if ($item->seatId) {
            $this->seatService->reserveSeat($item->seatId);
        }

        $lineTotal = $item->unitPrice * $item->quantity;

        $this->orderRepository->createItem([
            'order_id' => $order->id,
            'event_ticket_id' => $item->eventTicketId,
            'sector_id' => $item->sectorId,
            'batch_id' => $item->batchId,
            'seat_id' => $item->seatId,
            'quantity' => $item->quantity,
            'unit_price' => $item->unitPrice,
            'total_price' => $lineTotal,
        ]);

        $this->orderRepository->createReservation([
            'event_ticket_id' => $item->eventTicketId,
            'order_id' => $order->id,
            'seat_id' => $item->seatId,
            'batch_id' => $item->batchId,
            'quantity' => $item->quantity,
            'status' => ReservationStatus::RESERVED,
            'expires_at' => $expiresAt,
        ]);

        if (! $item->batchId) {
            $updatedTicket = $this->eventTicketRepository->decrementQuantity($ticket, $item->quantity);
            $this->availabilityCache->setAvailable($ticket->id, $updatedTicket->quantity);
        }
    }

    private function dispatchPostCheckoutJobs(Order $order): void
    {
        CreatePaymentJob::dispatch($order->id)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::PAYMENTS_CREATE);

        SendPurchaseEmailJob::dispatch($order->id, PurchaseCreatedMail::class)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::EMAILS);

        $order->load('reservations');

        foreach ($order->reservations as $reservation) {
            ExpireTicketReservationJob::dispatch($reservation->id)
                ->onConnection(config('queue.default'))
                ->onQueue(QueueNames::TICKETS_EXPIRATION)
                ->delay(now()->addMinutes(config('checkout.reservation_ttl_minutes')));
        }
    }

    /**
     * @param  CheckoutLineItem[]  $items
     */
    private function rollbackFailedPayment(Order $order, array $items): void
    {
        DB::transaction(function () use ($order, $items) {
            $this->orderRepository->failPayment($order);

            foreach ($order->reservations as $reservation) {
                $this->orderRepository->updateReservation($reservation, [
                    'status' => ReservationStatus::EXPIRED,
                ]);
            }

            foreach ($items as $item) {
                if ($item->batchId) {
                    $batch = TicketBatch::find($item->batchId);

                    if ($batch) {
                        $this->batchService->incrementBatchStock($batch, $item->quantity);
                    }
                } else {
                    $ticket = $this->eventTicketRepository->releaseTickets($item->eventTicketId, $item->quantity);
                    $this->availabilityCache->setAvailable($ticket->id, $ticket->quantity);
                }

                if ($item->seatId) {
                    $this->seatService->releaseSeat($item->seatId);
                }
            }
        });
    }
}
