<?php

namespace App\Exceptions\Analytics;

use App\Exceptions\BaseException;

class RangeTooLargeException extends BaseException
{
    protected int $httpStatusCode = 400;

    protected string $logLevel = 'info';

    public function errorCode(): string
    {
        return 'RANGE_TOO_LARGE';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), $this->getContext() ? ['errors' => $this->getContext()] : []);
    }
}
