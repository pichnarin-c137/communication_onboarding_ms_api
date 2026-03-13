<?php

namespace App\Exceptions;

class InvalidTokenException extends BaseException
{
    protected int $httpStatusCode = 401;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Invalid or malformed token', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
