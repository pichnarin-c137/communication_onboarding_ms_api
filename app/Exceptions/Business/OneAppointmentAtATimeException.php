<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class OneAppointmentAtATimeException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Finish the current appointment before starting a new one', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
