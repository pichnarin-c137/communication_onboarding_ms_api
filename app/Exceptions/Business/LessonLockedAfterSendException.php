<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class LessonLockedAfterSendException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(string $message = 'Lesson cannot be modified after it has been sent', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
