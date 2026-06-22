<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\CartRepository;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected CheckoutItemResolver $itemResolver,
    ) {
    }

    public function getCart(User $user, ?int $eventId = null): Collection
    {
        return $this->cartRepository->getForUser($user->id, $eventId);
    }

    public function addItem(User $user, array $payload): CartItem
    {
        $resolved = $this->itemResolver->resolve([$payload])[0];
        $ticket = $resolved->eventTicketId;

        $existing = $this->cartRepository->findLine(
            $user->id,
            $resolved->eventTicketId,
            $resolved->batchId,
            $resolved->seatId
        );

        if ($existing && ! $resolved->seatId) {
            return $this->cartRepository->update($existing, [
                'quantity' => $existing->quantity + $resolved->quantity,
                'unit_price' => $resolved->unitPrice,
            ]);
        }

        return $this->cartRepository->create([
            'user_id' => $user->id,
            'event_id' => \App\Models\TicketEvent::findOrFail($resolved->eventTicketId)->event_id,
            'event_ticket_id' => $resolved->eventTicketId,
            'sector_id' => $resolved->sectorId,
            'batch_id' => $resolved->batchId,
            'seat_id' => $resolved->seatId,
            'quantity' => $resolved->quantity,
            'unit_price' => $resolved->unitPrice,
        ]);
    }

    public function updateItem(User $user, int $itemId, int $quantity): CartItem
    {
        $item = $this->cartRepository->findForUser($user->id, $itemId);

        if ($quantity <= 0) {
            $this->cartRepository->delete($item);

            return $item;
        }

        return $this->cartRepository->update($item, ['quantity' => $quantity]);
    }

    public function removeItem(User $user, int $itemId): void
    {
        $item = $this->cartRepository->findForUser($user->id, $itemId);
        $this->cartRepository->delete($item);
    }

    public function clear(User $user, ?int $eventId = null): void
    {
        $this->cartRepository->clearForUser($user->id, $eventId);
    }

    /**
     * @return CheckoutLineItem[]
     */
    public function toCheckoutItems(User $user, ?int $eventId = null): array
    {
        $items = $this->getCart($user, $eventId);

        return $items->map(fn (CartItem $item) => new CheckoutLineItem(
            eventTicketId: $item->event_ticket_id,
            quantity: $item->quantity,
            batchId: $item->batch_id,
            seatId: $item->seat_id,
            sectorId: $item->sector_id,
            unitPrice: $item->unit_price,
        ))->all();
    }
}
