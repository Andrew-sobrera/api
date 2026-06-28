<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AsaasException;
use App\Exceptions\BaseException;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gerencia cancelamentos e estornos de pagamentos no Asaas.
 */
class AsaasRefundService
{
    public function __construct(
        private readonly AsaasClient $client,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * Cancela/estorna um pedido:
     *  1. Cancela ou estorna a cobrança no Asaas
     *  2. Atualiza o pedido localmente
     *
     * @throws BaseException
     * @throws AsaasException
     */
    public function refundOrder(Order $order, ?string $reason = null): Order
    {
        if (! $order->asaas_payment_id) {
            throw new BaseException('Pedido sem cobrança Asaas. Não é possível estornar.', 422);
        }

        if ($order->payment_status?->value === PaymentStatus::CANCELLED->value) {
            throw new BaseException('Pedido já está cancelado.', 422);
        }

        Log::info('[AsaasRefundService] Iniciando estorno', [
            'order_id' => $order->id,
            'asaas_payment_id' => $order->asaas_payment_id,
            'current_status' => $order->payment_status?->value,
        ]);

        DB::transaction(function () use ($order) {
            $currentStatus = $order->payment_status?->value;

            if (in_array($currentStatus, ['PAID', 'CONFIRMED'], true)) {
                $this->requestRefund($order);
            } else {
                $this->cancelPayment($order);
            }

            $this->orderRepository->update($order, [
                'status' => OrderStatus::CANCELLED,
                'payment_status' => PaymentStatus::CANCELLED,
                'refunded_at' => now(),
            ]);
        });

        Log::info('[AsaasRefundService] Estorno concluído', [
            'order_id' => $order->id,
        ]);

        return $order->fresh();
    }

    /**
     * Solicita estorno parcial de um valor específico.
     */
    public function partialRefund(Order $order, int $amountCents): Order
    {
        if (! $order->asaas_payment_id) {
            throw new BaseException('Pedido sem cobrança Asaas.', 422);
        }

        $value = round($amountCents / 100, 2);

        $response = $this->client->refundPayment($order->asaas_payment_id, $value);

        $this->client->logTransaction(
            type: 'REFUND',
            status: 'REQUESTED',
            requestPayload: ['payment_id' => $order->asaas_payment_id, 'value' => $value],
            responsePayload: $response,
            asaasPaymentId: $order->asaas_payment_id,
            orderId: $order->id,
            amount: $amountCents,
        );

        Log::info('[AsaasRefundService] Estorno parcial solicitado', [
            'order_id' => $order->id,
            'value_cents' => $amountCents,
        ]);

        return $order;
    }

    private function requestRefund(Order $order): void
    {
        $response = $this->client->refundPayment($order->asaas_payment_id);

        $this->client->logTransaction(
            type: 'REFUND',
            status: 'REQUESTED',
            requestPayload: ['payment_id' => $order->asaas_payment_id],
            responsePayload: $response,
            asaasPaymentId: $order->asaas_payment_id,
            orderId: $order->id,
            amount: $order->total_amount,
        );
    }

    private function cancelPayment(Order $order): void
    {
        $response = $this->client->cancelPayment($order->asaas_payment_id);

        $this->client->logTransaction(
            type: 'PAYMENT',
            status: 'CANCELLED',
            requestPayload: ['payment_id' => $order->asaas_payment_id],
            responsePayload: $response,
            asaasPaymentId: $order->asaas_payment_id,
            orderId: $order->id,
            amount: $order->total_amount,
        );
    }
}
