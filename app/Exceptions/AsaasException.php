<?php

namespace App\Exceptions;

use Throwable;

class AsaasException extends BaseException
{
    public function __construct(
        string $message = 'Erro na integração com o Asaas.',
        int $code = 502,
        private readonly ?array $asaasResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getAsaasResponse(): ?array
    {
        return $this->asaasResponse;
    }

    public static function fromResponse(array $response, int $httpStatus = 502): self
    {
        $errors = $response['errors'] ?? [];
        $description = $errors[0]['description'] ?? ($response['message'] ?? 'Erro desconhecido no Asaas.');

        return new self($description, $httpStatus, $response);
    }
}
