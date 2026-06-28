<?php

namespace App\DTOs;

/**
 * Dados retornados pelo Asaas ao criar uma subconta.
 */
readonly class AsaasAccountData
{
    public function __construct(
        public string $accountId,
        public string $walletId,
        public string $status,
        public ?string $apiKey = null,
    ) {
    }

    public static function fromAsaasResponse(array $response): self
    {
        return new self(
            accountId: $response['id'],
            walletId: $response['walletId'] ?? $response['id'],
            status: $response['status'] ?? 'PENDING',
            apiKey: $response['apiKey'] ?? null,
        );
    }
}
