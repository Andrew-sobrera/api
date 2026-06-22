<?php

namespace App\Exceptions;

class InsufficientStockException extends BaseException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Insufficient ticket stock available.', 409);
    }
}
