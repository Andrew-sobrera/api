<?php

namespace App\Services;

use App\DTOs\CheckoutFeeBreakdown;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class OrderPaymentService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected PaymentGatewayInterface $paymentGateway
    ) {
    }

    public function processPayment(
        int $orderId,
        ?string $cardToken = null,
        ?CheckoutFeeBreakdown $breakdown = null,
    ): Order {
        $order = $this->orderRepository->findById($orderId);

        if ($order->asaas_payment_id) {
            return $order;
        }

        $payment = match ($order->payment_method) {
            PaymentMethod::PIX => $this->paymentGateway->createPixPayment($order, $breakdown),
            PaymentMethod::CREDIT_CARD => $this->processCreditCardPayment($order, $cardToken, $breakdown),
        };

        return $this->persistPaymentResponse($order, $payment, $breakdown);
    }

    public function createAsaasPayment(int $orderId): void
    {
        $this->processPayment($orderId);
    }

    private function processCreditCardPayment(
        Order $order,
        ?string $cardToken,
        ?CheckoutFeeBreakdown $breakdown,
    ): array {
        if (! $cardToken) {
            throw new \InvalidArgumentException('Token do cartão é obrigatório para pagamento com cartão de crédito.');
        }

        return $this->paymentGateway->createCreditCardPayment($order, $cardToken, $breakdown);
    }

    private function persistPaymentResponse(
        Order $order,
        array $payment,
        ?CheckoutFeeBreakdown $breakdown,
    ): Order {
        $data = [
            'asaas_payment_id' => $payment['id'],
            'payment_response' => $payment,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
        ];

        if ($order->payment_method === PaymentMethod::PIX) {
            $data['pix_payload'] = $payment['pix_payload'] ?? $payment['payload'] ?? null;
            $data['pix_qr_code_url'] = $payment['pix_qr_code_url'] ?? null;
        }

        // Persiste o breakdown de taxas se disponível
        if ($breakdown) {
            $data['ticket_amount'] = $breakdown->ticketAmount;
            $data['gateway_fee'] = $breakdown->gatewayFee;
            $data['platform_commission'] = $breakdown->platformCommission;
            $data['producer_amount'] = $breakdown->producerAmount;
            $data['installments'] = $breakdown->installments;
            $data['payment_fee_mode'] = $breakdown->paymentFeeMode;
        }

        if ($this->isImmediatelyConfirmed($payment)) {
            $data['payment_status'] = PaymentStatus::PAID;
            $data['status'] = OrderStatus::PAID;
        }

        $order = $this->orderRepository->updatePaymentData($order, $data);

        Log::info('Asaas payment created', [
            'order_id' => $order->id,
            'asaas_payment_id' => $payment['id'],
            'payment_method' => $order->payment_method->value,
            'total_amount' => $order->total_amount,
            'gateway_fee' => $breakdown?->gatewayFee,
            'platform_commission' => $breakdown?->platformCommission,
        ]);

        return $order;
    }

    private function isImmediatelyConfirmed(array $payment): bool
    {
        return in_array(strtoupper($payment['status'] ?? ''), ['CONFIRMED', 'RECEIVED'], true);
    }
}
