<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use App\Support\CartIdentity;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService)
    {
    }

    /** @deprecated Use showByUuid or storeItem */
    public function index(Request $request)
    {
        $items = $this->cartService->getCart(
            $request->user(),
            $request->integer('event_id') ?: null
        );

        return CartItemResource::collection($items);
    }

    public function showByUuid(Request $request, string $cartId)
    {
        $identity = CartIdentity::fromRequest($request);
        $cart = $this->cartService->showCart($identity, $cartId);

        return new CartResource($cart);
    }

    public function storeItem(CartItemRequest $request)
    {
        $identity = CartIdentity::fromRequest($request);
        $cart = $this->cartService->addItemForIdentity($identity, $request->validated());

        return (new CartResource($cart))->response()->setStatusCode(201);
    }

    public function updateItem(CartItemRequest $request, int $id)
    {
        $identity = CartIdentity::fromRequest($request);
        $cart = $this->cartService->updateItemForIdentity(
            $identity,
            $id,
            (int) $request->input('quantity')
        );

        return new CartResource($cart);
    }

    public function destroyItem(Request $request, int $id)
    {
        $identity = CartIdentity::fromRequest($request);
        $cart = $this->cartService->removeItemForIdentity($identity, $id);

        return new CartResource($cart);
    }

    public function merge(Request $request)
    {
        $request->validate([
            'cart_id' => ['required', 'uuid'],
        ]);

        $cart = $this->cartService->mergeGuestCart(
            $request->user(),
            $request->input('cart_id')
        );

        if (! $cart) {
            return response()->json(['message' => 'Carrinho inválido ou expirado.'], 404);
        }

        return new CartResource($cart);
    }

    /** @deprecated Use storeItem */
    public function store(CartItemRequest $request)
    {
        $item = $this->cartService->addItem($request->user(), $request->validated());

        return (new CartItemResource($item))->response()->setStatusCode(201);
    }

    /** @deprecated Use updateItem */
    public function update(CartItemRequest $request, int $id)
    {
        $item = $this->cartService->updateItem(
            $request->user(),
            $id,
            (int) $request->input('quantity')
        );

        return new CartItemResource($item);
    }

    /** @deprecated Use destroyItem */
    public function destroy(Request $request, int $id)
    {
        $this->cartService->removeItem($request->user(), $id);

        return response()->noContent();
    }

    public function clear(Request $request)
    {
        $this->cartService->clear(
            $request->user(),
            $request->integer('event_id') ?: null
        );

        return response()->noContent();
    }
}
