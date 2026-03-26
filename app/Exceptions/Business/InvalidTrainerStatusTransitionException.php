<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class InvalidTrainerStatusTransitionException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Invalid trainer status transition.', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
