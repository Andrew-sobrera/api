<?php

namespace App\Services;

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

    public function processPayment(int $orderId, ?string $cardToken = null): Order
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order->asaas_payment_id) {
            return $order;
        }

        $payment = match ($order->payment_method) {
            PaymentMethod::PIX => $this->paymentGateway->createPixPayment($order),
            PaymentMethod::CREDIT_CARD => $this->processCreditCardPayment($order, $cardToken),
        };

        return $this->persistPaymentResponse($order, $payment);
    }

    public function createAsaasPayment(int $orderId): void
    {
        $this->processPayment($orderId);
    }

    private function processCreditCardPayment(Order $order, ?string $cardToken): array
    {
        if (! $cardToken) {
            throw new \InvalidArgumentException('Token do cartão é obrigatório para pagamento com cartão de crédito.');
        }

        return $this->paymentGateway->createCreditCardPayment($order, $cardToken);
    }

    private function persistPaymentResponse(Order $order, array $payment): Order
    {
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

        if ($this->isImmediatelyConfirmed($payment)) {
            $data['payment_status'] = PaymentStatus::PAID;
            $data['status'] = OrderStatus::PAID;
        }

        $order = $this->orderRepository->updatePaymentData($order, $data);

        Log::info('Asaas payment created', [
            'order_id' => $order->id,
            'asaas_payment_id' => $payment['id'],
            'payment_method' => $order->payment_method->value,
        ]);

        return $order;
    }

    private function isImmediatelyConfirmed(array $payment): bool
    {
        return in_array(strtoupper($payment['status'] ?? ''), ['CONFIRMED', 'RECEIVED'], true);
    }
}
