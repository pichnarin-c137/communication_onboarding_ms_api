<?php

namespace App\Exceptions\Business;

use App\Exceptions\BaseException;
use Throwable;

class InvalidUserRoleForRosterException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'warning';

    public function __construct(
        string $message = 'Only sale users can hold a dedicated trainer roster, and only trainer users can be assigned to it.',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
