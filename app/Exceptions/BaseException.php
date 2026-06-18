<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BaseException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message, int $code = 400, ?Throwable $previous = null)
    {
        $this->statusCode = $code;

        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->getStatusCode(),
        ], $this->getStatusCode());
    }
}
