<?php

namespace App\Services\Payments;

use App\DTOs\AsaasAccountData;
use App\Enums\AsaasAccountStatus;
use App\Exceptions\AsaasException;
use App\Models\Producer;
use Illuminate\Support\Facades\Log;

/**
 * Gerencia subcontas de produtores no Asaas.
 *
 * Subconta = conta vinculada à conta principal da plataforma.
 * O walletId retornado é usado no split de pagamentos.
 */
class AsaasAccountService
{
    public function __construct(private readonly AsaasClient $client)
    {
    }

    /**
     * Cria uma subconta Asaas para o produtor e persiste os dados retornados.
     *
     * @throws AsaasException
     */
    public function createSubaccount(Producer $producer): AsaasAccountData
    {
        if ($producer->hasAsaasAccount()) {
            Log::info('[AsaasAccountService] Subconta já existe para o produtor', [
                'producer_id' => $producer->id,
                'asaas_account_id' => $producer->asaas_account_id,
            ]);

            return new AsaasAccountData(
                accountId: $producer->asaas_account_id,
                walletId: $producer->asaas_wallet_id,
                status: $producer->asaas_status?->value ?? 'ACTIVE',
            );
        }

        $payload = $this->buildSubaccountPayload($producer);

        Log::info('[AsaasAccountService] Criando subconta Asaas', [
            'producer_id' => $producer->id,
            'cpfCnpj' => $payload['cpfCnpj'] ?? null,
        ]);

        $response = $this->client->createSubaccount($payload);

        $this->client->logTransaction(
            type: 'SUBCONTA',
            status: 'CREATED',
            requestPayload: $payload,
            responsePayload: $response,
            producerId: $producer->id,
        );

        $accountData = AsaasAccountData::fromAsaasResponse($response);

        $producer->update([
            'asaas_account_id' => $accountData->accountId,
            'asaas_wallet_id' => $accountData->walletId,
            'asaas_status' => AsaasAccountStatus::from(strtoupper($accountData->status)),
            'asaas_onboarding_completed' => true,
            'asaas_created_at' => now(),
        ]);

        Log::info('[AsaasAccountService] Subconta criada com sucesso', [
            'producer_id' => $producer->id,
            'asaas_account_id' => $accountData->accountId,
            'wallet_id' => $accountData->walletId,
        ]);

        return $accountData;
    }

    /**
     * Sincroniza o status da subconta Asaas com o banco de dados.
     */
    public function syncAccountStatus(Producer $producer): void
    {
        if (! $producer->hasAsaasAccount()) {
            return;
        }

        $response = $this->client->getSubaccountStatus($producer->asaas_account_id);
        $status = strtoupper($response['status'] ?? 'INACTIVE');

        $asaasStatus = AsaasAccountStatus::tryFrom($status) ?? AsaasAccountStatus::INACTIVE;

        $producer->update([
            'asaas_status' => $asaasStatus,
        ]);

        Log::info('[AsaasAccountService] Status sincronizado', [
            'producer_id' => $producer->id,
            'status' => $asaasStatus->value,
        ]);
    }

    /**
     * Verifica se o produtor pode processar pagamentos.
     */
    public function isReadyForPayments(Producer $producer): bool
    {
        if (config('asaas.bypass_producer_validation')) {
            return true;
        }

        return $producer->isAsaasReady();
    }

    /**
     * Retorna o saldo disponível para saque pelo Asaas.
     */
    public function getBalance(Producer $producer): array
    {
        if (! $producer->hasAsaasAccount()) {
            return ['balance' => 0, 'error' => 'Subconta não configurada.'];
        }

        return $this->client->getBalance($producer->asaas_wallet_id);
    }

    private function buildSubaccountPayload(Producer $producer): array
    {
        $document = preg_replace('/\D/', '', $producer->cnpj ?? '');
        $address = $producer->address ?? [];

        if ($producer->income_value === null || (float) $producer->income_value <= 0) {
            throw new AsaasException(
                'Informe o faturamento ou renda mensal antes de criar a subconta Asaas.',
                422,
            );
        }

        $payload = [
            'name' => $producer->name,
            'email' => $producer->email,
            'cpfCnpj' => $document,
            'incomeValue' => (float) $producer->income_value,
            'phone' => preg_replace('/\D/', '', $producer->phone ?? ''),
            'mobilePhone' => preg_replace('/\D/', '', $producer->phone ?? ''),
            'address' => $address['street'] ?? null,
            'addressNumber' => $address['number'] ?? null,
            'complement' => $address['complement'] ?? null,
            'province' => $address['district'] ?? null,
            'postalCode' => preg_replace('/\D/', '', $address['postal_code'] ?? ''),
        ];

        if (strlen($document) === 14) {
            $payload['companyType'] = 'MEI';
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== '',
        );
    }
}
