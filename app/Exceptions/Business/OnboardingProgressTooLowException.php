<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class OnboardingProgressTooLowException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Onboarding progress must be at least 90% before marking as complete', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
