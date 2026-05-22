<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class TrainerWorkloadExceededException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'The trainer cannot be assigned because their workload exceeds the configured limit.',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
