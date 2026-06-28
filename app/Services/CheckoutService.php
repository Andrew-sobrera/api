<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProducerNotReadyException;
use App\Jobs\CreatePaymentJob;
use App\Jobs\ExpireTicketReservationJob;
use App\Jobs\SendPurchaseEmailJob;
use App\Mail\PurchaseCreatedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Repositories\EventTicketRepository;
use App\Repositories\OrderRepository;
use App\Services\Payments\AsaasFeeCalculatorService;
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
        protected AsaasFeeCalculatorService $feeCalculator,
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
        int $installments = 1,
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
            $order = DB::transaction(function () use ($userId, $items, $paymentMethod, $installments) {
                $totalTicketAmount = 0;
                $eventId = null;

                foreach ($items as $item) {
                    $totalTicketAmount += $item->unitPrice * $item->quantity;
                    $ticket = $this->eventTicketRepository->findForUpdate($item->eventTicketId);
                    $eventId = $ticket->event_id;
                }

                // Calcular taxas e breakdown financeiro
                $event = Event::with('producer')->findOrFail($eventId);
                $producer = $event->producer;

                if ($producer && ! config('asaas.bypass_producer_validation') && ! $producer->isAsaasReady()) {
                    throw new ProducerNotReadyException();
                }

                $breakdown = $producer
                    ? $this->feeCalculator->calculateForProducer(
                        $totalTicketAmount,
                        $paymentMethod->value,
                        $producer,
                        $installments,
                    )
                    : null;

                $totalAmount = $breakdown ? $breakdown->totalCustomerAmount : $totalTicketAmount;
                $expiresAt = now()->addMinutes(config('checkout.reservation_ttl_minutes'));

                $order = $this->orderRepository->createOrder([
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_method' => $paymentMethod,
                    'total_amount' => $totalAmount,
                    'ticket_amount' => $totalTicketAmount,
                    'gateway_fee' => $breakdown?->gatewayFee ?? 0,
                    'platform_commission' => $breakdown?->platformCommission ?? 0,
                    'producer_amount' => $breakdown?->producerAmount ?? 0,
                    'installments' => $installments,
                    'payment_fee_mode' => $breakdown?->paymentFeeMode,
                ]);

                foreach ($items as $item) {
                    $this->processLineItem($order, $item, $expiresAt);
                }

                return ['order' => $order->load(['items.eventTicket', 'reservations', 'user']), 'breakdown' => $breakdown];
            });
        } catch (\Throwable $exception) {
            foreach ($items as $item) {
                $this->availabilityCache->release($item->eventTicketId, $item->quantity);
            }

            throw $exception;
        }

        $resolvedOrder = $order['order'];
        $breakdown = $order['breakdown'] ?? null;

        try {
            $resolvedOrder = $this->orderPaymentService->processPayment($resolvedOrder->id, $cardToken, $breakdown);
        } catch (\Throwable $exception) {
            $this->rollbackFailedPayment($resolvedOrder, $items);
            Log::error('Checkout payment failed', [
                'order_id' => $resolvedOrder->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->dispatchPostCheckoutJobs($resolvedOrder);

        if ($clearCart) {
            $this->cartService->clear(\App\Models\User::findOrFail($userId), $eventId ?? null);
        }

        return $resolvedOrder;
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

    public function checkoutFromCart(
        int $userId,
        PaymentMethod $paymentMethod,
        ?string $cardToken = null,
        ?int $eventId = null,
        int $installments = 1,
    ): Order {
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
            $result = DB::transaction(function () use ($userId, $items, $paymentMethod, $cart, $installments) {
                $totalTicketAmount = 0;
                $resolvedEventId = null;

                foreach ($items as $item) {
                    $totalTicketAmount += $item->unitPrice * $item->quantity;
                    $ticket = $this->eventTicketRepository->findForUpdate($item->eventTicketId);
                    $resolvedEventId = $ticket->event_id;
                }

                $event = Event::with('producer')->findOrFail($resolvedEventId);
                $producer = $event->producer;

                if ($producer && ! config('asaas.bypass_producer_validation') && ! $producer->isAsaasReady()) {
                    throw new ProducerNotReadyException();
                }

                $breakdown = $producer
                    ? $this->feeCalculator->calculateForProducer(
                        $totalTicketAmount,
                        $paymentMethod->value,
                        $producer,
                        $installments,
                    )
                    : null;

                $totalAmount = $breakdown ? $breakdown->totalCustomerAmount : $totalTicketAmount;

                $order = $this->orderRepository->createOrder([
                    'user_id' => $userId,
                    'event_id' => $resolvedEventId,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_method' => $paymentMethod,
                    'total_amount' => $totalAmount,
                    'ticket_amount' => $totalTicketAmount,
                    'gateway_fee' => $breakdown?->gatewayFee ?? 0,
                    'platform_commission' => $breakdown?->platformCommission ?? 0,
                    'producer_amount' => $breakdown?->producerAmount ?? 0,
                    'installments' => $installments,
                    'payment_fee_mode' => $breakdown?->paymentFeeMode,
                ]);

                foreach ($items as $item) {
                    $this->processLineItemFromCart($order, $item);
                }

                app(CartReservationService::class)->transferCartToOrder($cart, $order);

                return ['order' => $order->load(['items.eventTicket', 'reservations', 'user']), 'breakdown' => $breakdown];
            });
        } catch (\Throwable $exception) {
            throw $exception;
        }

        $resolvedOrder = $result['order'];
        $breakdown = $result['breakdown'] ?? null;

        try {
            $resolvedOrder = $this->orderPaymentService->processPayment($resolvedOrder->id, $cardToken, $breakdown);
        } catch (\Throwable $exception) {
            $this->rollbackFailedPayment($resolvedOrder, $items);
            Log::error('Checkout payment failed', [
                'order_id' => $resolvedOrder->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->dispatchPostCheckoutJobs($resolvedOrder);
        $this->cartService->clear($user, $eventId);

        return $resolvedOrder;
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
