<?php

namespace App\Exceptions;

class ProducerNotReadyException extends BaseException
{
    public function __construct(string $message = 'Produtor não possui conta Asaas ativa para receber pagamentos.')
    {
        parent::__construct($message, 422);
    }
}
