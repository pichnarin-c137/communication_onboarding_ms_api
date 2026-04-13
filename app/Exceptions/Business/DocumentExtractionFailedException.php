<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;

class DocumentExtractionFailedException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'Could not extract text from the uploaded document.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
