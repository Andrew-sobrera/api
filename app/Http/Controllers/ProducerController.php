<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteFinancialProfileRequest;
use App\Http\Requests\ProducerPaymentSettingsRequest;
use App\Http\Resources\ProducerResource;
use App\Services\ProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProducerController extends Controller
{
    public function __construct(private readonly ProducerService $producerService)
    {
    }

    /**
     * Retorna os dados do produtor autenticado.
     */
    public function show(Request $request): ProducerResource
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        return new ProducerResource($producer->load('paymentMethods'));
    }

    /**
     * Completa o perfil financeiro (produtores Google OAuth sem CNPJ).
     */
    public function completeFinancialProfile(CompleteFinancialProfileRequest $request): ProducerResource
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $updated = $this->producerService->completeFinancialProfile($producer, $request->validated());

        return new ProducerResource($updated->load('paymentMethods'));
    }

    /**
     * Atualiza configurações de pagamento:
     *  - payment_fee_mode
     *  - métodos de pagamento aceitos
     */
    public function updatePaymentSettings(ProducerPaymentSettingsRequest $request): ProducerResource
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $updated = $this->producerService->updatePaymentSettings($producer, $request->validated());

        return new ProducerResource($updated->load('paymentMethods'));
    }
}
