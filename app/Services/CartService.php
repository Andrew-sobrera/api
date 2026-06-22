<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Exceptions\InsufficientStockException;
use App\Jobs\ExpireCartReservationJob;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\CartRepository;
use App\Support\QueueNames;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected CheckoutItemResolver $itemResolver,
        protected CartReservationService $cartReservationService,
    ) {
    }

    public function getCart(User $user, ?int $eventId = null): Collection
    {
        return $this->cartRepository->getForUser($user->id, $eventId);
    }

    public function addItem(User $user, array $payload): CartItem
    {
        $resolved = $this->itemResolver->resolve([$payload])[0];
        $eventId = \App\Models\TicketEvent::findOrFail($resolved->eventTicketId)->event_id;

        $cart = $this->cartRepository->getOrCreateActiveCart($user->id, $eventId);
        $cart = $this->cartRepository->extendCart($cart);

        $existing = $this->cartRepository->findLine(
            $user->id,
            $resolved->eventTicketId,
            $resolved->batchId,
            $resolved->seatId
        );

        if ($existing && ! $resolved->seatId) {
            $this->cartReservationService->releaseForCartItem($existing);

            $line = new CheckoutLineItem(
                eventTicketId: $resolved->eventTicketId,
                quantity: $existing->quantity + $resolved->quantity,
                batchId: $resolved->batchId,
                seatId: $resolved->seatId,
                sectorId: $resolved->sectorId,
                unitPrice: $resolved->unitPrice,
            );

            $this->cartReservationService->reserveForItem($cart, $line);

            return $this->cartRepository->update($existing, [
                'quantity' => $line->quantity,
                'unit_price' => $resolved->unitPrice,
                'cart_id' => $cart->id,
            ]);
        }

        if ($existing && $resolved->seatId) {
            throw new InsufficientStockException('Assento já está no carrinho.');
        }

        $this->cartReservationService->reserveForItem($cart, $resolved);

        $item = $this->cartRepository->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'event_id' => $eventId,
            'event_ticket_id' => $resolved->eventTicketId,
            'sector_id' => $resolved->sectorId,
            'batch_id' => $resolved->batchId,
            'seat_id' => $resolved->seatId,
            'quantity' => $resolved->quantity,
            'unit_price' => $resolved->unitPrice,
        ]);

        $this->scheduleExpiration($cart->id);

        return $item->load(['eventTicket', 'sector', 'batch', 'seat', 'event', 'cart']);
    }

    public function updateItem(User $user, int $itemId, int $quantity): CartItem
    {
        $item = $this->cartRepository->findForUser($user->id, $itemId);

        if ($quantity <= 0) {
            $this->removeItem($user, $itemId);

            return $item;
        }

        if (! $item->cart_id || ! $item->cart?->isActive()) {
            throw new InsufficientStockException('Carrinho expirado. Adicione os itens novamente.');
        }

        $this->cartReservationService->releaseForCartItem($item);

        $line = new CheckoutLineItem(
            eventTicketId: $item->event_ticket_id,
            quantity: $quantity,
            batchId: $item->batch_id,
            seatId: $item->seat_id,
            sectorId: $item->sector_id,
            unitPrice: $item->unit_price,
        );

        $cart = $this->cartRepository->extendCart($item->cart);
        $this->cartReservationService->reserveForItem($cart, $line);
        $this->scheduleExpiration($cart->id);

        return $this->cartRepository->update($item, ['quantity' => $quantity]);
    }

    public function removeItem(User $user, int $itemId): void
    {
        $item = $this->cartRepository->findForUser($user->id, $itemId);
        $this->cartReservationService->releaseForCartItem($item);
        $this->cartRepository->delete($item);
    }

    public function clear(User $user, ?int $eventId = null): void
    {
        $items = $this->cartRepository->getForUser($user->id, $eventId);

        foreach ($items as $item) {
            $this->cartReservationService->releaseForCartItem($item);
        }

        $this->cartRepository->clearForUser($user->id, $eventId);

        if ($eventId) {
            \App\Models\Cart::query()
                ->where('user_id', $user->id)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }
    }

    /**
     * @return CheckoutLineItem[]
     */
    public function toCheckoutItems(User $user, ?int $eventId = null): array
    {
        $items = $this->getCart($user, $eventId);

        if ($items->isEmpty()) {
            throw new InsufficientStockException('Carrinho vazio.');
        }

        foreach ($items as $item) {
            if (! $item->cart?->isActive()) {
                throw new InsufficientStockException('Carrinho expirado. Adicione os itens novamente.');
            }
        }

        return $items->map(fn (CartItem $item) => new CheckoutLineItem(
            eventTicketId: $item->event_ticket_id,
            quantity: $item->quantity,
            batchId: $item->batch_id,
            seatId: $item->seat_id,
            sectorId: $item->sector_id,
            unitPrice: $item->unit_price,
        ))->all();
    }

    public function activeCartForUserEvent(int $userId, int $eventId): ?\App\Models\Cart
    {
        return \App\Models\Cart::query()
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function scheduleExpiration(int $cartId): void
    {
        $cart = \App\Models\Cart::findOrFail($cartId);

        ExpireCartReservationJob::dispatch($cartId)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::TICKETS_EXPIRATION)
            ->delay($cart->expires_at);
    }
}
