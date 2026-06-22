<?php

namespace App\Services\Payments;

use App\Exceptions\BaseException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasPaymentGateway implements PaymentGatewayInterface
{
    public function createPixPayment(Order $order): array
    {
        $payment = $this->createCharge($order, 'PIX');

        if (empty($payment['id'])) {
            return $payment;
        }

        $pixQrCode = $this->fetchPixQrCode($payment['id']);

        return array_merge($payment, [
            'pix_payload' => $pixQrCode['payload'] ?? null,
            'pix_qr_code_url' => isset($pixQrCode['encodedImage'])
                ? 'data:image/png;base64,'.$pixQrCode['encodedImage']
                : null,
        ]);
    }

    public function createCreditCardPayment(Order $order, string $cardToken): array
    {
        return $this->createCharge($order, 'CREDIT_CARD', $cardToken);
    }

    public function getPaymentStatus(string $id): array
    {
        $response = Http::withHeaders([
            'access_token' => config('asaas.api_key'),
        ])->get(config('asaas.url').'/payments/'.$id);

        if ($response->failed()) {
            Log::error('Asaas payment status fetch failed', [
                'payment_id' => $id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        }

        return $response->json();
    }

    private function createCharge(Order $order, string $billingType, ?string $cardToken = null): array
    {
        $order->loadMissing('user');

        $payload = [
            'customer' => $this->getOrCreateCustomer($order->user),
            'billingType' => $billingType,
            'value' => round($order->total_amount / 100, 2),
            'dueDate' => now()->addDays(config('asaas.payment_due_days'))->format('Y-m-d'),
            'externalReference' => (string) $order->id,
            'description' => 'Pedido #'.$order->id,
        ];

        if ($billingType === 'CREDIT_CARD') {
            $payload['creditCardToken'] = $cardToken;
        }

        $response = Http::withHeaders([
            'access_token' => config('asaas.api_key'),
            'Content-Type' => 'application/json',
        ])->post(config('asaas.url').'/payments', $payload);

        if ($response->failed()) {
            Log::error('Asaas payment creation failed', [
                'order_id' => $order->id,
                'billing_type' => $billingType,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        }

        return $response->json();
    }

    private function fetchPixQrCode(string $paymentId): array
    {
        $response = Http::withHeaders([
            'access_token' => config('asaas.api_key'),
        ])->get(config('asaas.url').'/payments/'.$paymentId.'/pixQrCode');

        if ($response->failed()) {
            Log::error('Asaas PIX QR code fetch failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        }

        return $response->json();
    }

    private function getOrCreateCustomer(User $user): string
    {
        if (! $user->document) {
            throw new BaseException('CPF ou CNPJ do usuário é obrigatório para criar cobrança no Asaas.', 422);
        }

        $document = preg_replace('/\D/', '', $user->document);

        $existing = Http::withHeaders([
            'access_token' => config('asaas.api_key'),
        ])->get(config('asaas.url').'/customers', [
            'email' => $user->email,
        ]);

        if ($existing->successful()) {
            $customers = $existing->json('data', []);

            if (! empty($customers[0]['id'])) {
                $customerId = $customers[0]['id'];

                if (empty($customers[0]['cpfCnpj'])) {
                    $this->updateCustomerDocument($customerId, $document);
                }

                return $customerId;
            }
        }

        $response = Http::withHeaders([
            'access_token' => config('asaas.api_key'),
            'Content-Type' => 'application/json',
        ])->post(config('asaas.url').'/customers', [
            'name' => $user->name,
            'email' => $user->email,
            'cpfCnpj' => $document,
        ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json('id');
    }

    private function updateCustomerDocument(string $customerId, string $document): void
    {
        Http::withHeaders([
            'access_token' => config('asaas.api_key'),
            'Content-Type' => 'application/json',
        ])->put(config('asaas.url').'/customers/'.$customerId, [
            'cpfCnpj' => $document,
        ])->throw();
    }
}
