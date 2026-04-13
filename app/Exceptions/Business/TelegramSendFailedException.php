<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class TelegramSendFailedException extends BaseException
{
    protected int $httpStatusCode = 500;

    protected string $logLevel = 'error';

    public function __construct(
        string $message = 'Failed to send message to Telegram.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
