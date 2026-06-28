<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Models\Producer;
use App\Models\ProducerPaymentMethod;
use App\Repositories\ProducerRepository;
use App\Services\Payments\AsaasAccountService;
use App\Services\Payments\AsaasFeeCalculatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProducerService
{
    public function __construct(
        private readonly ProducerRepository $producerRepository,
        private readonly AsaasAccountService $asaasAccountService,
        private readonly AsaasFeeCalculatorService $feeCalculator,
    ) {
    }

    /**
     * Retorna o produtor do usuário autenticado.
     */
    public function getForUser(int $userId): Producer
    {
        return $this->producerRepository->findByUserId($userId);
    }

    /**
     * Completa o perfil financeiro de um produtor (Google OAuth incompleto).
     * Cria a subconta Asaas após completar os dados.
     */
    public function completeFinancialProfile(Producer $producer, array $data): Producer
    {
        DB::transaction(function () use ($producer, $data) {
            $producer->update([
                'cnpj' => $data['cnpj'] ?? $producer->cnpj,
                'fantasy_name' => $data['fantasy_name'] ?? $producer->fantasy_name,
                'phone' => $data['phone'] ?? $producer->phone,
                'email' => $data['email'] ?? $producer->email,
                'address' => $data['address'] ?? $producer->address,
            ]);

            $this->asaasAccountService->createSubaccount($producer->fresh());
        });

        return $producer->fresh();
    }

    /**
     * Atualiza as configurações de pagamento do produtor:
     *  - payment_fee_mode (CUSTOMER/PRODUCER)
     *  - métodos de pagamento aceitos
     *  - parcelamento máximo
     */
    public function updatePaymentSettings(Producer $producer, array $data): Producer
    {
        DB::transaction(function () use ($producer, $data) {
            $producer->update(array_filter([
                'payment_fee_mode' => $data['payment_fee_mode'] ?? null,
                'ticket_commission_percentage' => $data['ticket_commission_percentage'] ?? null,
            ]));

            if (isset($data['payment_methods'])) {
                $this->syncPaymentMethods($producer, $data['payment_methods']);
            }
        });

        return $producer->fresh(['paymentMethods']);
    }

    /**
     * Sincroniza os métodos de pagamento do produtor.
     */
    private function syncPaymentMethods(Producer $producer, array $methods): void
    {
        $allowedMethods = ['PIX', 'CREDIT_CARD', 'BOLETO', 'DEBIT_CARD'];

        // Remove todos os métodos atuais
        $producer->paymentMethods()->delete();

        foreach ($methods as $method) {
            if (! in_array(strtoupper($method['payment_method'] ?? ''), $allowedMethods, true)) {
                continue;
            }

            ProducerPaymentMethod::create([
                'producer_id' => $producer->id,
                'payment_method' => strtoupper($method['payment_method']),
                'max_installments' => $method['max_installments'] ?? 1,
                'active' => $method['active'] ?? true,
            ]);
        }
    }

    /**
     * Calcula breakdown financeiro para a calculadora do produtor.
     */
    public function calculateBreakdown(Producer $producer, array $params): array
    {
        $ticketAmount = (int) round((float) $params['ticket_price'] * 100);
        $quantity = (int) ($params['quantity'] ?? 1);
        $totalTicketAmount = $ticketAmount * $quantity;
        $paymentType = strtoupper($params['payment_type'] ?? 'PIX');
        $installments = (int) ($params['installments'] ?? 1);
        $feeMode = $params['fee_mode'] ?? $producer->payment_fee_mode?->value ?? 'CUSTOMER';
        $commissionPct = (float) $producer->ticket_commission_percentage;

        $breakdown = $this->feeCalculator->calculate(
            $totalTicketAmount,
            $paymentType,
            $commissionPct,
            $feeMode,
            $installments,
        );

        return $breakdown->toFormattedArray();
    }

    /**
     * Retorna os pedidos dos eventos do produtor.
     */
    public function getProducerOrders(Producer $producer, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->producerRepository->getOrdersForProducer($producer->id, $filters);
    }

    /**
     * Retorna dashboard financeiro buscando saldo do Asaas.
     */
    public function getFinancialDashboard(Producer $producer): array
    {
        if (! $producer->hasAsaasAccount()) {
            return [
                'asaas_ready' => false,
                'message' => 'Conta Asaas não configurada.',
            ];
        }

        try {
            $balance = $this->asaasAccountService->getBalance($producer);
        } catch (\Throwable $e) {
            Log::warning('[ProducerService] Falha ao buscar saldo Asaas', [
                'producer_id' => $producer->id,
                'error' => $e->getMessage(),
            ]);

            $balance = ['balance' => 0, 'error' => 'Erro ao buscar saldo.'];
        }

        $pendingOrders = $this->producerRepository->getPendingOrdersAmount($producer->id);

        return [
            'asaas_ready' => true,
            'asaas_status' => $producer->asaas_status?->value,
            'available_balance' => $balance['balance'] ?? 0,
            'pending_amount' => $pendingOrders,
        ];
    }
}
