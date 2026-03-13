<?php

namespace App\Exceptions;

class OtpExpiredException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'info';

    public function __construct(string $message = 'OTP has expired', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
