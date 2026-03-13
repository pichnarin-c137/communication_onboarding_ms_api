<?php

namespace App\Exceptions;

class UserNotFoundException extends BaseException
{
    protected int $httpStatusCode = 404;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'User not found', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
