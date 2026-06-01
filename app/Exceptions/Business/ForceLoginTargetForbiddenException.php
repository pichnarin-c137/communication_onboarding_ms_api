<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class ForceLoginTargetForbiddenException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Force login is only allowed for sale and trainer accounts', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
