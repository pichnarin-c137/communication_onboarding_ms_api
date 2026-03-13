<?php

namespace App\Exceptions;

class AdminOnlyException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Admin access required', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
