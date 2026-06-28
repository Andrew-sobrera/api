<?php

namespace App\Jobs;

use App\Models\Producer;
use App\Services\Payments\AsaasAccountService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateAsaasSubaccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(public int $producerId)
    {
        $this->onQueue(QueueNames::ASAAS_SUBACCOUNTS);
    }

    public function handle(AsaasAccountService $accountService): void
    {
        $producer = Producer::findOrFail($this->producerId);

        if ($producer->hasAsaasAccount()) {
            Log::info('[CreateAsaasSubaccountJob] Subconta já existe, pulando.', [
                'producer_id' => $this->producerId,
            ]);

            return;
        }

        if (! $producer->income_value || empty($producer->address)) {
            Log::info('[CreateAsaasSubaccountJob] Perfil financeiro incompleto, aguardando cadastro.', [
                'producer_id' => $this->producerId,
            ]);

            return;
        }

        Log::info('[CreateAsaasSubaccountJob] Criando subconta Asaas', [
            'producer_id' => $this->producerId,
        ]);

        $accountService->createSubaccount($producer);

        Log::info('[CreateAsaasSubaccountJob] Subconta criada com sucesso', [
            'producer_id' => $this->producerId,
            'asaas_account_id' => $producer->fresh()->asaas_account_id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[CreateAsaasSubaccountJob] Falha permanente ao criar subconta', [
            'producer_id' => $this->producerId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
