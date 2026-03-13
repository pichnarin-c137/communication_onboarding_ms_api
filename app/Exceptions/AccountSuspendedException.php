<?php

namespace App\Exceptions;

class AccountSuspendedException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Account has been suspended', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
