<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class ProofRequiredException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Proof photo is required for this action', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
