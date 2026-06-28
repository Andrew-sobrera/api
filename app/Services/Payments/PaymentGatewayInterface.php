<?php

namespace App\Services\Payments;

use App\DTOs\CheckoutFeeBreakdown;
use App\Models\Order;

interface PaymentGatewayInterface
{
    public function createPixPayment(Order $order, ?CheckoutFeeBreakdown $breakdown = null): array;

    public function createCreditCardPayment(Order $order, string $cardToken, ?CheckoutFeeBreakdown $breakdown = null): array;

    public function getPaymentStatus(string $id): array;
}
