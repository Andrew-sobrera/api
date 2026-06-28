<?php

namespace App\Services\Payments;

use App\DTOs\CheckoutFeeBreakdown;
use App\Exceptions\BaseException;
use App\Models\Order;
use App\Models\User;

class AsaasPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly AsaasClient $client)
    {
    }

    public function createPixPayment(Order $order, ?CheckoutFeeBreakdown $breakdown = null): array
    {
        $payment = $this->createCharge($order, 'PIX', null, $breakdown);

        if (empty($payment['id'])) {
            return $payment;
        }

        $pixQrCode = $this->client->getPixQrCode($payment['id']);

        return array_merge($payment, [
            'pix_payload' => $pixQrCode['payload'] ?? null,
            'pix_qr_code_url' => isset($pixQrCode['encodedImage'])
                ? 'data:image/png;base64,'.$pixQrCode['encodedImage']
                : null,
        ]);
    }

    public function createCreditCardPayment(Order $order, string $cardToken, ?CheckoutFeeBreakdown $breakdown = null): array
    {
        return $this->createCharge($order, 'CREDIT_CARD', $cardToken, $breakdown);
    }

    public function getPaymentStatus(string $id): array
    {
        return $this->client->getPayment($id);
    }

    private function createCharge(
        Order $order,
        string $billingType,
        ?string $cardToken = null,
        ?CheckoutFeeBreakdown $breakdown = null,
    ): array {
        $order->loadMissing(['user', 'event.producer']);

        $chargeValue = $breakdown
            ? round($breakdown->totalCustomerAmount / 100, 2)
            : round($order->total_amount / 100, 2);

        $payload = [
            'customer' => $this->getOrCreateCustomer($order->user),
            'billingType' => $billingType,
            'value' => $chargeValue,
            'dueDate' => now()->addDays(config('asaas.payment_due_days'))->format('Y-m-d'),
            'externalReference' => (string) $order->id,
            'description' => 'Pedido #'.$order->id,
        ];

        if ($billingType === 'CREDIT_CARD') {
            if (! $cardToken) {
                throw new \InvalidArgumentException('Token do cartão é obrigatório para pagamento com cartão de crédito.');
            }

            $payload['creditCardToken'] = $cardToken;

            if ($breakdown && $breakdown->installments > 1) {
                $payload['installmentCount'] = $breakdown->installments;
                $payload['installmentValue'] = round($chargeValue / $breakdown->installments, 2);
            }
        }

        // Split: repassa valor ao produtor se tiver walletId configurado
        $producer = $order->event?->producer;

        if ($producer && $producer->asaas_wallet_id && $breakdown) {
            $splitService = app(AsaasSplitService::class);
            $payload['split'] = $splitService->buildSplitPayload($producer, $breakdown);
        }

        $response = $this->client->createPayment($payload);

        // Auditoria
        $this->client->logTransaction(
            type: 'PAYMENT',
            status: $response['status'] ?? 'PENDING',
            requestPayload: array_merge($payload, ['cardToken' => '[REDACTED]']),
            responsePayload: $response,
            asaasPaymentId: $response['id'] ?? null,
            orderId: $order->id,
            producerId: $producer?->id,
            eventId: $order->event_id,
            amount: $order->total_amount,
        );

        return $response;
    }

    private function getOrCreateCustomer(User $user): string
    {
        if (! $user->document) {
            throw new BaseException('CPF ou CNPJ do usuário é obrigatório para criar cobrança no Asaas.', 422);
        }

        $document = preg_replace('/\D/', '', $user->document);

        $existing = $this->client->findCustomerByEmail($user->email);

        if ($existing) {
            if (empty($existing['cpfCnpj'])) {
                $this->client->updateCustomer($existing['id'], ['cpfCnpj' => $document]);
            }

            return $existing['id'];
        }

        $customer = $this->client->createCustomer([
            'name' => $user->name,
            'email' => $user->email,
            'cpfCnpj' => $document,
        ]);

        return $customer['id'];
    }
}
