<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Exceptions\InsufficientStockException;
use App\Jobs\ExpireCartReservationJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\CartRepository;
use App\Support\CartIdentity;
use App\Support\QueueNames;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected CheckoutItemResolver $itemResolver,
        protected CartReservationService $cartReservationService,
        protected CartExpirationService $cartExpirationService,
    ) {
    }

    public function getCart(User $user, ?int $eventId = null): Collection
    {
        return $this->cartRepository->getForUser($user->id, $eventId);
    }

    public function getCartForIdentity(CartIdentity $identity, ?int $eventId = null): ?Cart
    {
        if ($identity->user) {
            $items = $this->cartRepository->getForUser($identity->user->id, $eventId);

            if ($items->isEmpty()) {
                return null;
            }

            $cart = $items->first()->cart;

            return $cart ? $this->cartRepository->loadCartWithItems($cart) : null;
        }

        if (! $identity->cartUuid) {
            return null;
        }

        $cart = $this->cartRepository->findByUuid($identity->cartUuid);

        if (! $cart || ! $cart->isActive()) {
            return null;
        }

        return $this->cartRepository->loadCartWithItems($cart);
    }

    public function showCart(CartIdentity $identity, string $cartUuid): Cart
    {
        $cart = $this->cartRepository->findByUuid($cartUuid);

        if (! $cart) {
            throw new NotFoundHttpException('Carrinho não encontrado.');
        }

        if (! $identity->canAccess($cart)) {
            throw new AuthorizationException('Acesso negado ao carrinho.');
        }

        if (! $cart->isActive()) {
            throw new InsufficientStockException('Carrinho expirado. Adicione os itens novamente.');
        }

        return $this->cartRepository->loadCartWithItems($cart);
    }

    public function addItem(User $user, array $payload): CartItem
    {
        $cart = $this->addItemForIdentity(new CartIdentity($user, null), $payload);

        return $cart->items->last();
    }

    public function addItemForIdentity(CartIdentity $identity, array $payload): Cart
    {
        $resolved = $this->itemResolver->resolve([$this->normalizePayload($payload)])[0];
        $eventId = \App\Models\TicketEvent::findOrFail($resolved->eventTicketId)->event_id;

        $guestCart = $identity->cartUuid
            ? $this->cartRepository->findByUuid($identity->cartUuid)
            : null;

        if ($guestCart && ! $identity->canAccess($guestCart)) {
            throw new AuthorizationException('Acesso negado ao carrinho.');
        }

        $cart = $this->cartRepository->resolveActiveCart(
            $identity->user?->id,
            $eventId,
            $guestCart
        );

        $existing = $this->cartRepository->findLineInCart(
            $cart->id,
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

            $this->cartRepository->update($existing, [
                'quantity' => $line->quantity,
                'unit_price' => $resolved->unitPrice,
                'cart_id' => $cart->id,
                'user_id' => $identity->user?->id,
            ]);
        } elseif ($existing && $resolved->seatId) {
            throw new InsufficientStockException('Assento já está no carrinho.');
        } else {
            $this->cartReservationService->reserveForItem($cart, $resolved);

            $this->cartRepository->create([
                'user_id' => $identity->user?->id,
                'cart_id' => $cart->id,
                'event_id' => $eventId,
                'event_ticket_id' => $resolved->eventTicketId,
                'sector_id' => $resolved->sectorId,
                'batch_id' => $resolved->batchId,
                'seat_id' => $resolved->seatId,
                'quantity' => $resolved->quantity,
                'unit_price' => $resolved->unitPrice,
            ]);
        }

        $this->scheduleExpiration($cart->id);

        return $this->cartRepository->loadCartWithItems(
            $cart->fresh()
        );
    }

    public function updateItem(User $user, int $itemId, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item = $this->cartRepository->findForUser($user->id, $itemId);
            $this->removeItem($user, $itemId);

            return $item;
        }

        $cart = $this->updateItemForIdentity(new CartIdentity($user, null), $itemId, $quantity);

        return $cart->items->firstWhere('id', $itemId) ?? $cart->items->last();
    }

    public function updateItemForIdentity(CartIdentity $identity, int $itemId, int $quantity): Cart
    {
        $item = $this->resolveItem($identity, $itemId);

        if ($quantity <= 0) {
            $cart = $item->cart;
            $this->removeItemForIdentity($identity, $itemId);

            return $cart
                ? $this->cartRepository->loadCartWithItems($cart->fresh())
                : $this->emptyCartResponse($item);
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
        $this->cartRepository->update($item, ['quantity' => $quantity]);

        return $this->cartRepository->loadCartWithItems($cart->fresh());
    }

    public function removeItem(User $user, int $itemId): void
    {
        $this->removeItemForIdentity(new CartIdentity($user, null), $itemId);
    }

    public function removeItemForIdentity(CartIdentity $identity, int $itemId): Cart
    {
        $item = $this->resolveItem($identity, $itemId);
        $cart = $item->cart;
        $this->cartReservationService->releaseForCartItem($item);
        $this->cartRepository->delete($item);

        if (! $cart) {
            throw new NotFoundHttpException('Carrinho não encontrado.');
        }

        return $this->cartRepository->loadCartWithItems($cart->fresh());
    }

    public function clear(User $user, ?int $eventId = null): void
    {
        $items = $this->cartRepository->getForUser($user->id, $eventId);

        foreach ($items as $item) {
            $this->cartReservationService->releaseForCartItem($item);
        }

        $this->cartRepository->clearForUser($user->id, $eventId);

        if ($eventId) {
            Cart::query()
                ->where('user_id', $user->id)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }
    }

    public function mergeGuestCart(User $user, string $cartUuid): ?Cart
    {
        $guestCart = $this->cartRepository->findByUuid($cartUuid);

        if (! $guestCart || ! $guestCart->isActive()) {
            return null;
        }

        if ($guestCart->user_id && $guestCart->user_id !== $user->id) {
            throw new AuthorizationException('Carrinho pertence a outro usuário.');
        }

        $userCart = $this->activeCartForUserEvent($user->id, $guestCart->event_id);

        if ($userCart && $userCart->id !== $guestCart->id) {
            foreach ($guestCart->items as $item) {
                $this->addItem($user, [
                    'event_ticket_id' => $item->event_ticket_id,
                    'quantity' => $item->quantity,
                    'batch_id' => $item->batch_id,
                    'seat_id' => $item->seat_id,
                    'sector_id' => $item->sector_id,
                ]);
            }

            $this->cartExpirationService->expireCart($guestCart->id);

            return $this->cartRepository->loadCartWithItems(
                $this->activeCartForUserEvent($user->id, $guestCart->event_id)
            );
        }

        $guestCart->update(['user_id' => $user->id]);
        $guestCart->items()->whereNull('user_id')->update(['user_id' => $user->id]);

        return $this->cartRepository->loadCartWithItems($guestCart->fresh());
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

    public function activeCartForUserEvent(int $userId, int $eventId): ?Cart
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function resolveItem(CartIdentity $identity, int $itemId): CartItem
    {
        if ($identity->user) {
            $item = $this->cartRepository->findForUser($identity->user->id, $itemId);
        } elseif ($identity->cartUuid) {
            $cart = $this->cartRepository->findByUuid($identity->cartUuid);

            if (! $cart) {
                throw new NotFoundHttpException('Carrinho não encontrado.');
            }

            $item = $this->cartRepository->findForCart($cart, $itemId);
        } else {
            throw new AuthorizationException('Identificação do carrinho é obrigatória.');
        }

        if ($item->cart && ! $identity->canAccess($item->cart)) {
            throw new AuthorizationException('Acesso negado ao carrinho.');
        }

        return $item;
    }

    private function normalizePayload(array $payload): array
    {
        if (isset($payload['ticket_id']) && ! isset($payload['event_ticket_id'])) {
            $payload['event_ticket_id'] = $payload['ticket_id'];
        }

        unset($payload['ticket_id'], $payload['cart_id']);

        return $payload;
    }

    private function emptyCartResponse(CartItem $item): Cart
    {
        $cart = $item->cart ?? Cart::make([
            'uuid' => $item->cart?->uuid ?? '',
            'status' => 'active',
            'event_id' => $item->event_id,
            'expires_at' => now(),
        ]);

        $cart->setRelation('items', collect());

        return $cart;
    }

    private function scheduleExpiration(int $cartId): void
    {
        $cart = Cart::findOrFail($cartId);

        ExpireCartReservationJob::dispatch($cartId)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::TICKETS_EXPIRATION)
            ->delay($cart->expires_at);
    }
}
