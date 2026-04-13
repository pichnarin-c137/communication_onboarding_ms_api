<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class PlaylistEmptyException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'Cannot send an empty playlist. Add at least one video first.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
