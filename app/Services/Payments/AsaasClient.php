<?php

namespace App\Services\Payments;

use App\Exceptions\AsaasException;
use App\Models\AsaasTransaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP centralizado para a API do Asaas.
 *
 * Responsabilidades:
 *  - Autenticar todas as requisições com a API key correta
 *  - Logar request/response na tabela asaas_transactions
 *  - Aplicar retry em falhas transitórias
 *  - Lançar AsaasException em erros da API
 */
class AsaasClient
{
    public function __construct(private readonly ?string $apiKey = null)
    {
    }

    // ─────────────────────────────────── Clientes / Subcontas ────

    public function findCustomerByEmail(string $email): ?array
    {
        $response = $this->get('/customers', ['email' => $email]);
        $data = $response->json('data', []);

        return $data[0] ?? null;
    }

    public function createCustomer(array $payload): array
    {
        return $this->post('/customers', $payload)->json();
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        return $this->put("/customers/{$customerId}", $payload)->json();
    }

    public function createSubaccount(array $payload): array
    {
        return $this->post('/accounts', $payload)->json();
    }

    public function getSubaccountStatus(string $accountId): array
    {
        return $this->get("/accounts/{$accountId}")->json();
    }

    // ───────────────────────────────────────────── Pagamentos ────

    public function createPayment(array $payload): array
    {
        return $this->post('/payments', $payload)->json();
    }

    public function getPayment(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}")->json();
    }

    public function getPixQrCode(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}/pixQrCode")->json();
    }

    public function refundPayment(string $paymentId, ?float $value = null): array
    {
        $payload = $value !== null ? ['value' => $value] : [];

        return $this->post("/payments/{$paymentId}/refund", $payload)->json();
    }

    public function cancelPayment(string $paymentId): array
    {
        return $this->delete("/payments/{$paymentId}")->json();
    }

    // ─────────────────────────────────────── Saldo / Financeiro ────

    public function getBalance(string $walletId): array
    {
        return $this->get('/finance/balance')->json();
    }

    public function getTransfers(?array $filters = []): array
    {
        return $this->get('/transfers', $filters)->json();
    }

    // ─────────────────────────────────────────── HTTP helpers ────

    public function get(string $path, array $query = []): Response
    {
        return $this->executeRequest('GET', $path, $query);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->executeRequest('POST', $path, $payload);
    }

    public function put(string $path, array $payload = []): Response
    {
        return $this->executeRequest('PUT', $path, $payload);
    }

    public function delete(string $path): Response
    {
        return $this->executeRequest('DELETE', $path);
    }

    private function executeRequest(string $method, string $path, array $data = []): Response
    {
        $url = config('asaas.url').$path;
        $apiKey = $this->apiKey ?? config('asaas.api_key');

        $logContext = [
            'method' => $method,
            'path' => $path,
            'payload_keys' => array_keys($data),
        ];

        Log::debug('[Asaas] Request', $logContext);

        try {
            $request = $this->buildRequest($apiKey);

            $response = match ($method) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->failed()) {
                $body = $response->json() ?? [];

                Log::error('[Asaas] Request failed', array_merge($logContext, [
                    'http_status' => $response->status(),
                    'response' => $body,
                ]));

                throw AsaasException::fromResponse($body, $response->status() >= 500 ? 502 : $response->status());
            }

            Log::debug('[Asaas] Response OK', array_merge($logContext, [
                'http_status' => $response->status(),
            ]));

            return $response;
        } catch (AsaasException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[Asaas] HTTP exception', array_merge($logContext, [
                'error' => $e->getMessage(),
            ]));

            throw new AsaasException('Falha na comunicação com o Asaas: '.$e->getMessage(), 502, null, $e);
        }
    }

    private function buildRequest(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'access_token' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(config('asaas.timeout', 30))
            ->retry(
                config('asaas.retry_times', 3),
                config('asaas.retry_sleep', 1000),
                fn (\Throwable $e, PendingRequest $req) => $this->shouldRetry($e),
                throw: false,
            );
    }

    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof AsaasException) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }

    /**
     * Registra a transação na tabela asaas_transactions para auditoria.
     */
    public function logTransaction(
        string $type,
        string $status,
        array $requestPayload,
        array $responsePayload,
        ?string $asaasPaymentId = null,
        ?int $orderId = null,
        ?int $producerId = null,
        ?int $eventId = null,
        int $amount = 0,
        int $feeAmount = 0,
        int $netAmount = 0,
        ?string $errorMessage = null,
    ): AsaasTransaction {
        return AsaasTransaction::create([
            'asaas_payment_id' => $asaasPaymentId,
            'producer_id' => $producerId,
            'order_id' => $orderId,
            'event_id' => $eventId,
            'type' => $type,
            'status' => $status,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'error_message' => $errorMessage,
        ]);
    }
}
