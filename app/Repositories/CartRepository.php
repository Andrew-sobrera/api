<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Str;

class CartRepository
{
    public function __construct(protected CartItem $model, protected Cart $cartModel)
    {
    }

    public function findByUuid(string $uuid): ?Cart
    {
        return $this->cartModel->newQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    public function getForUser(int $userId, ?int $eventId = null): \Illuminate\Support\Collection
    {
        $query = $this->model->newQuery()
            ->with(['eventTicket', 'sector', 'batch', 'seat', 'event', 'cart'])
            ->where('user_id', $userId);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        return $query->get();
    }

    public function getForCart(Cart $cart): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->with(['eventTicket', 'sector', 'batch', 'seat', 'event', 'cart'])
            ->where('cart_id', $cart->id)
            ->get();
    }

    public function findForUser(int $userId, int $itemId): CartItem
    {
        return $this->model->newQuery()
            ->with('cart')
            ->where('user_id', $userId)
            ->findOrFail($itemId);
    }

    public function findForCart(Cart $cart, int $itemId): CartItem
    {
        return $this->model->newQuery()
            ->with('cart')
            ->where('cart_id', $cart->id)
            ->findOrFail($itemId);
    }

    public function findLine(int $userId, int $eventTicketId, ?int $batchId, ?int $seatId): ?CartItem
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('event_ticket_id', $eventTicketId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when(! $batchId, fn ($q) => $q->whereNull('batch_id'))
            ->when($seatId, fn ($q) => $q->where('seat_id', $seatId))
            ->when(! $seatId, fn ($q) => $q->whereNull('seat_id'))
            ->first();
    }

    public function findLineInCart(int $cartId, int $eventTicketId, ?int $batchId, ?int $seatId): ?CartItem
    {
        return $this->model->newQuery()
            ->where('cart_id', $cartId)
            ->where('event_ticket_id', $eventTicketId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when(! $batchId, fn ($q) => $q->whereNull('batch_id'))
            ->when($seatId, fn ($q) => $q->where('seat_id', $seatId))
            ->when(! $seatId, fn ($q) => $q->whereNull('seat_id'))
            ->first();
    }

    public function getOrCreateActiveCart(int $userId, int $eventId): Cart
    {
        $cart = $this->cartModel->newQuery()
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($cart) {
            return $cart;
        }

        return $this->cartModel->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'event_id' => $eventId,
            'status' => 'active',
            'expires_at' => now()->addMinutes(config('checkout.cart_ttl_minutes')),
        ]);
    }

    public function createGuestCart(int $eventId): Cart
    {
        return $this->cartModel->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'event_id' => $eventId,
            'status' => 'active',
            'expires_at' => now()->addMinutes(config('checkout.cart_ttl_minutes')),
        ]);
    }

    public function resolveActiveCart(?int $userId, int $eventId, ?Cart $guestCart = null): Cart
    {
        if ($guestCart && $guestCart->isActive() && $guestCart->event_id === $eventId) {
            if ($userId && ! $guestCart->user_id) {
                $guestCart->update(['user_id' => $userId]);
                $this->model->newQuery()
                    ->where('cart_id', $guestCart->id)
                    ->update(['user_id' => $userId]);
            }

            return $this->extendCart($guestCart);
        }

        if ($userId) {
            return $this->getOrCreateActiveCart($userId, $eventId);
        }

        return $this->createGuestCart($eventId);
    }

    public function extendCart(Cart $cart): Cart
    {
        $cart->update([
            'expires_at' => now()->addMinutes(config('checkout.cart_ttl_minutes')),
        ]);

        return $cart->fresh();
    }

    public function findExpiredCarts()
    {
        return $this->cartModel->newQuery()
            ->with(['items', 'reservations'])
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();
    }

    public function create(array $data): CartItem
    {
        return $this->model->create($data);
    }

    public function update(CartItem $item, array $data): CartItem
    {
        $item->update($data);

        return $item->fresh(['eventTicket', 'sector', 'batch', 'seat', 'event', 'cart']);
    }

    public function delete(CartItem $item): void
    {
        $item->delete();
    }

    public function clearForUser(int $userId, ?int $eventId = null): void
    {
        $query = $this->model->newQuery()->where('user_id', $userId);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $query->delete();
    }

    public function clearForCart(Cart $cart): void
    {
        $this->model->newQuery()->where('cart_id', $cart->id)->delete();
    }

    public function markCartExpired(Cart $cart): void
    {
        $cart->update(['status' => 'expired']);
    }

    public function loadCartWithItems(Cart $cart): Cart
    {
        return $cart->load(['items.eventTicket', 'items.sector', 'items.batch', 'items.seat', 'items.event']);
    }
}
