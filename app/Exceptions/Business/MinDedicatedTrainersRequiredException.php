<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class MinDedicatedTrainersRequiredException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'The sale user must have at least the minimum number of dedicated trainers assigned.',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
