<?php

namespace App\Services;

use App\Models\Cart;
use App\Repositories\CartRepository;

class CartExpirationService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected CartReservationService $cartReservationService,
    ) {
    }

    public function expireCart(int $cartId): void
    {
        $cart = Cart::with(['items', 'reservations'])->find($cartId);

        if (! $cart || $cart->status !== 'active') {
            return;
        }

        if ($cart->expires_at->isFuture()) {
            return;
        }

        foreach ($cart->reservations as $reservation) {
            $this->cartReservationService->releaseReservation($reservation);
        }

        $cart->items()->delete();
        $this->cartRepository->markCartExpired($cart);
    }

    public function expireDueCarts(): void
    {
        $carts = $this->cartRepository->findExpiredCarts();

        foreach ($carts as $cart) {
            $this->expireCart($cart->id);
        }
    }
}
