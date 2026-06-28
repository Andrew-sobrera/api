<?php

namespace App\Services\Payments;

use App\DTOs\CheckoutFeeBreakdown;
use App\Exceptions\AsaasException;
use App\Models\Producer;
use Illuminate\Support\Facades\Log;

/**
 * Monta o payload de split de pagamento para o Asaas.
 *
 * Regra:
 *  - A plataforma cria a cobrança pela conta principal
 *  - O split repassa ao produtor: (ticket_amount - platform_commission)
 *  - Comissão é sempre calculada sobre o valor BRUTO dos ingressos
 *
 * Referência Asaas:
 *  split: [{ walletId: "producerWalletId", fixedValue: 47.50 }]
 */
class AsaasSplitService
{
    /**
     * Monta o array de split para inclusão no payload de criação de cobrança.
     *
     * @throws AsaasException
     */
    public function buildSplitPayload(Producer $producer, CheckoutFeeBreakdown $breakdown): array
    {
        if (! $producer->asaas_wallet_id) {
            throw new AsaasException(
                'Produtor não possui walletId Asaas configurado para receber split.',
                422,
            );
        }

        $this->validateSplitValues($breakdown);

        $producerValue = round($breakdown->producerAmount / 100, 2);

        $split = [[
            'walletId' => $producer->asaas_wallet_id,
            'fixedValue' => $producerValue,
        ]];

        Log::debug('[AsaasSplitService] Split calculado', [
            'producer_id' => $producer->id,
            'wallet_id' => $producer->asaas_wallet_id,
            'producer_value' => $producerValue,
            'ticket_amount' => $breakdown->ticketAmount,
            'platform_commission' => $breakdown->platformCommission,
        ]);

        return $split;
    }

    /**
     * Calcula o valor que será retido pela plataforma (em centavos).
     */
    public function getPlatformRetentionCents(CheckoutFeeBreakdown $breakdown): int
    {
        return $breakdown->platformCommission;
    }

    /**
     * Valida que os valores do breakdown são consistentes.
     *
     * @throws AsaasException
     */
    private function validateSplitValues(CheckoutFeeBreakdown $breakdown): void
    {
        if ($breakdown->producerAmount < 0) {
            throw new AsaasException(
                'O valor do produtor ficou negativo após taxas. Verifique a configuração de taxas.',
                422,
            );
        }

        if ($breakdown->totalCustomerAmount < $breakdown->producerAmount) {
            throw new AsaasException(
                'Valor do cliente é menor que o valor do produtor. Inconsistência no cálculo de split.',
                422,
            );
        }
    }
}
