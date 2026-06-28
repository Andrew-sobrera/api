<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\CheckoutPreviewRequest;
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

        $installments = $request->integer('installments', 1);

        if ($request->boolean('from_cart')) {
            $order = $this->checkoutService->checkoutFromCart(
                $user->id,
                $paymentMethod,
                $cardToken,
                $request->integer('event_id') ?: null,
                $installments,
            );
        } elseif ($request->has('items')) {
            $items = $this->itemResolver->resolve($request->input('items'));
            $order = $this->checkoutService->checkoutFromItems(
                $user->id,
                $items,
                $paymentMethod,
                $cardToken,
                false,
                null,
                $installments,
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

    public function preview(CheckoutPreviewRequest $request)
    {
        $paymentMethod = PaymentMethod::from($request->input('payment_method'));
        $installments = $request->integer('installments', 1);

        if ($request->boolean('from_cart')) {
            $breakdown = $this->checkoutService->previewFromCart(
                $request->user()->id,
                $paymentMethod,
                $request->integer('event_id') ?: null,
                $installments,
            );
        } elseif ($request->has('items')) {
            $items = $this->itemResolver->resolve($request->input('items'));
            $breakdown = $this->checkoutService->previewFromItems($items, $paymentMethod, $installments);
        } else {
            $items = $this->itemResolver->resolve([[
                'event_ticket_id' => $request->integer('event_ticket_id'),
                'quantity' => $request->integer('quantity'),
                'batch_id' => $request->integer('batch_id') ?: null,
                'seat_id' => $request->integer('seat_id') ?: null,
            ]]);
            $breakdown = $this->checkoutService->previewFromItems($items, $paymentMethod, $installments);
        }

        return response()->json(['data' => $breakdown->toFormattedArray()]);
    }
}
