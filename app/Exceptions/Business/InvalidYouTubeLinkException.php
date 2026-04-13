<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class InvalidYouTubeLinkException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'The provided URL is not a valid YouTube link.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
