<?php

namespace App\Rules;

use App\Models\Producer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que um produtor está habilitado para criar eventos.
 *
 * Condições:
 *  - Deve ter subconta Asaas criada
 *  - Status da conta deve ser ACTIVE
 *  - Pode ser bypassado via config (desenvolvimento/sandbox)
 */
class ProducerCanCreateEventRule implements ValidationRule
{
    public function __construct(private readonly Producer $producer)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (config('asaas.bypass_producer_validation')) {
            return;
        }

        if (! $this->producer->hasAsaasAccount()) {
            $fail('Você precisa configurar sua conta financeira antes de criar eventos.');

            return;
        }

        if (! $this->producer->isAsaasReady()) {
            $fail('Sua conta Asaas ainda não está ativa. Aguarde a ativação ou entre em contato com o suporte.');
        }
    }
}
