<?php

namespace App\Exceptions;

class InvalidCredentialsException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials', 401);
    }
}
