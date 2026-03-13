<?php

namespace App\Exceptions;

class RoleNotFoundException extends BaseException
{
    protected int $httpStatusCode = 404;

    protected string $logLevel = 'error';

    public function __construct(string $message = 'Role not found', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
