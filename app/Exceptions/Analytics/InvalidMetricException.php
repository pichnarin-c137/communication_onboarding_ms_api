<?php

namespace App\Exceptions\Analytics;

use App\Exceptions\BaseException;

class InvalidMetricException extends BaseException
{
    protected int $httpStatusCode = 422;

    protected string $logLevel = 'info';

    public function errorCode(): string
    {
        return 'INVALID_METRIC';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), $this->getContext() ? ['errors' => $this->getContext()] : []);
    }
}
