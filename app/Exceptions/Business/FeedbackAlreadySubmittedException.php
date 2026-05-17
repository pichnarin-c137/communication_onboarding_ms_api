<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class FeedbackAlreadySubmittedException extends BaseException
{
    protected int $httpStatusCode = 409;

    protected string $logLevel = 'info';

    public function __construct(
        string     $message = 'You have already submitted feedback for this session.',
        int        $code = 0,
        ?Throwable $previous = null,
        array      $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
