<?php

namespace App\Exceptions;

class OtpRateLimitException extends BaseException
{
    protected int $httpStatusCode = 429;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Too many OTP requests. Please wait before requesting another.', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
