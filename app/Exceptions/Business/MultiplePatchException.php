<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class MultiplePatchException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'You can only patch one resource at a time', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
