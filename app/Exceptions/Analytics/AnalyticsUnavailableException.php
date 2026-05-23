<?php

namespace App\Exceptions\Analytics;

use App\Exceptions\BaseException;

class AnalyticsUnavailableException extends BaseException
{
    protected int $httpStatusCode = 503;

    protected string $logLevel = 'warning';

    public function errorCode(): string
    {
        return 'ANALYTICS_UNAVAILABLE';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), $this->getContext() ? ['errors' => $this->getContext()] : []);
    }
}
