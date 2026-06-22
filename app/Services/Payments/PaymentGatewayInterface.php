<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGatewayInterface
{
    public function createPixPayment(Order $order): array;

    public function createCreditCardPayment(Order $order, string $cardToken): array;

    public function getPaymentStatus(string $id): array;
}
