<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class TelegramSetupException extends BaseException
{
    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'Telegram setup failed',
        int $httpStatusCode = 422,
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        $this->httpStatusCode = $httpStatusCode;

        parent::__construct($message, $code, $previous, $context);
    }
}
