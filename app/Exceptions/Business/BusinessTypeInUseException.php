<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class BusinessTypeInUseException extends BaseException
{
    protected int $httpStatusCode = 409;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'This business type is in use and cannot be deleted.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
