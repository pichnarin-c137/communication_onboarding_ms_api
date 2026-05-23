<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class TrainerHasActiveCommitmentsException extends BaseException
{
    protected int $httpStatusCode = 409;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'This trainer still has active commitments and cannot be suspended or removed until they are reassigned.',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
