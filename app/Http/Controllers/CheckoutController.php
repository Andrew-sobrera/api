<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CheckoutResource;
use App\Services\CheckoutItemResolver;
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected CheckoutItemResolver $itemResolver,
    ) {
    }

    public function store(CheckoutRequest $request)
    {
        $user = $request->user();

        if ($request->filled('document') && ! $user->document) {
            $user->update(['document' => preg_replace('/\D/', '', $request->input('document'))]);
        }

        if (! $user->document) {
            return response()->json([
                'error' => ['document' => ['CPF ou CNPJ é obrigatório para finalizar a compra.']],
            ], 422);
        }

        $paymentMethod = PaymentMethod::from($request->input('payment_method'));
        $cardToken = $request->input('card_token');

        if ($request->boolean('from_cart')) {
            $order = $this->checkoutService->checkoutFromCart(
                $user->id,
                $paymentMethod,
                $cardToken,
                $request->integer('event_id') ?: null
            );
        } elseif ($request->has('items')) {
            $items = $this->itemResolver->resolve($request->input('items'));
            $order = $this->checkoutService->checkoutFromItems(
                $user->id,
                $items,
                $paymentMethod,
                $cardToken
            );
        } else {
            $order = $this->checkoutService->checkout(
                $user->id,
                (int) $request->input('event_ticket_id'),
                (int) $request->input('quantity'),
                $paymentMethod,
                $cardToken,
                $request->integer('batch_id') ?: null,
                $request->integer('seat_id') ?: null,
            );
        }

        return (new CheckoutResource($order))->response()->setStatusCode(201);
    }
}
