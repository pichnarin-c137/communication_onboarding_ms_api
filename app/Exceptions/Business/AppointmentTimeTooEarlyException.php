<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class AppointmentTimeTooEarlyException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Appointment cannot be started yet — it is too early', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
