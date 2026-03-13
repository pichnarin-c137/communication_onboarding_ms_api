<?php

namespace App\Exceptions;

class JwtKeyNotFoundException extends BaseException
{
    protected int $httpStatusCode = 500;

    protected string $logLevel = 'critical';

    public function __construct(string $message = 'JWT key file not found', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
