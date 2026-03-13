<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class LeaveOfficeNotAllowedException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Leave office action is not allowed for online appointments', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
