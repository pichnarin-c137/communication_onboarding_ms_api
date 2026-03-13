<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class DefaultPolicyCannotBeRemovedException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Default policies cannot be removed', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
