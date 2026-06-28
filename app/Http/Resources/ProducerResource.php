<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProducerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fantasy_name' => $this->fantasy_name,
            'cnpj' => $this->cnpj,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,

            // Asaas
            'asaas_status' => $this->asaas_status?->value,
            'asaas_ready' => $this->isAsaasReady(),
            'asaas_onboarding_completed' => $this->asaas_onboarding_completed,
            'needs_financial_profile' => ! $this->hasAsaasAccount(),

            // Configurações financeiras
            'ticket_commission_percentage' => (float) $this->ticket_commission_percentage,
            'payment_fee_mode' => $this->payment_fee_mode?->value,
            'payment_methods' => $this->whenLoaded('paymentMethods', fn () => $this->paymentMethods->map(fn ($pm) => [
                'payment_method' => $pm->payment_method,
                'max_installments' => $pm->max_installments,
                'active' => $pm->active,
            ])),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
