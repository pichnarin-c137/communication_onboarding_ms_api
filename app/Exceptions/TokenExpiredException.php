<?php

namespace App\Exceptions;

class TokenExpiredException extends BaseException
{
    protected int $httpStatusCode = 401;

    protected string $logLevel = 'info';

    public function __construct(string $message = 'Token has expired', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
