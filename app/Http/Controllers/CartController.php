<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartItemResource;
use App\Http\Requests\CartItemRequest;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService)
    {
    }

    public function index(Request $request)
    {
        $items = $this->cartService->getCart(
            $request->user(),
            $request->integer('event_id') ?: null
        );

        return CartItemResource::collection($items);
    }

    public function store(CartItemRequest $request)
    {
        $item = $this->cartService->addItem($request->user(), $request->validated());

        return (new CartItemResource($item))->response()->setStatusCode(201);
    }

    public function update(CartItemRequest $request, int $id)
    {
        $item = $this->cartService->updateItem(
            $request->user(),
            $id,
            (int) $request->input('quantity')
        );

        return new CartItemResource($item);
    }

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
