<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class TrainerNotInSaleRosterException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'The selected trainer is not part of this sale user\'s dedicated roster.',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
