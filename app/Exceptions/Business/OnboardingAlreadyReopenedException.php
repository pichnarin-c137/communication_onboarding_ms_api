<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class OnboardingAlreadyReopenedException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'This onboarding has already been reopened.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'error_code' => 'ONBOARDING_ALREADY_REOPENED',
        ]);
    }
}
