<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class ClientFeedbackRequiredException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'Client feedback is required before completing the onboarding.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'error_code' => 'CLIENT_FEEDBACK_REQUIRED',
        ]);
    }
}
