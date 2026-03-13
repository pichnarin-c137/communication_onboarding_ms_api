<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseException extends Exception
{
    protected int $httpStatusCode = 500;

    protected string $logLevel = 'error';

    protected bool $shouldLog = true;

    protected array $context = [];

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;

        if ($this->shouldLog) {
            $this->logException();
        }
    }

    /**
     * Get the HTTP status code for this exception
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the context data for this exception
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert the exception to an array for JSON response
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => class_basename($this),
        ];
    }

    /**
     * Log the exception with context
     */
    protected function logException(): void
    {
        $logData = [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];

        Log::log($this->logLevel, $this->getMessage(), $logData);
    }
}
