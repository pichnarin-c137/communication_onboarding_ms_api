<?php

namespace App\Exceptions\Analytics;

use App\Exceptions\BaseException;

class ForbiddenScopeException extends BaseException
{
    protected int $httpStatusCode = 403;

    protected string $logLevel = 'info';

    public function errorCode(): string
    {
        return 'FORBIDDEN_SCOPE';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), $this->getContext() ? ['errors' => $this->getContext()] : []);
    }
}
